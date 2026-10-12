<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Every stock change goes through here so each branch keeps its own count
 * while products.currentStock stays the total across branches.
 */
class BranchInventory
{
    /** @var array<string, ?string> */
    private array $mainBranches = [];

    public function mainBranchId(string $tenantId): ?string
    {
        if (! array_key_exists($tenantId, $this->mainBranches)) {
            $this->mainBranches[$tenantId] = Branch::query()
                ->where('tenantId', $tenantId)
                ->orderByDesc('isMain')
                ->orderBy('createdAt')
                ->value('id');
        }

        return $this->mainBranches[$tenantId];
    }

    /**
     * @return Collection<int, Branch>
     */
    public function branches(string $tenantId): Collection
    {
        return Branch::query()
            ->where('tenantId', $tenantId)
            ->where('isActive', true)
            ->orderByDesc('isMain')
            ->orderBy('name')
            ->get(['id', 'name', 'isMain']);
    }

    /**
     * Resolves a requested branch to one that belongs to the tenant, falling back to the main branch.
     */
    public function resolveBranch(string $tenantId, ?string $branchId): ?string
    {
        if ($branchId && Branch::query()->where('tenantId', $tenantId)->whereKey($branchId)->exists()) {
            return $branchId;
        }

        return $this->mainBranchId($tenantId);
    }

    /**
     * @return array<string, int> branchId => quantity
     */
    public function quantities(Product $product): array
    {
        $rows = BranchStock::query()->where('productId', $product->id)->pluck('quantity', 'branchId')->map(fn ($qty) => (int) $qty)->all();

        $tracked = array_sum($rows);
        $untracked = (int) Product::query()->whereKey($product->id)->value('currentStock') - $tracked;
        if ($untracked !== 0 && $main = $this->mainBranchId($product->tenantId)) {
            $rows[$main] = ($rows[$main] ?? 0) + $untracked;
        }

        return $rows;
    }

    public function quantityAt(Product $product, ?string $branchId): int
    {
        $branchId = $branchId ?: $this->mainBranchId($product->tenantId);
        if (! $branchId) {
            return (int) Product::query()->whereKey($product->id)->value('currentStock');
        }

        return (int) ($this->quantities($product)[$branchId] ?? 0);
    }

    /**
     * @param  array<int, string>  $productIds
     * @return array<string, int> productId => quantity at the branch
     */
    public function quantitiesAt(string $tenantId, string $branchId, array $productIds): array
    {
        $main = $this->mainBranchId($tenantId);
        $rows = BranchStock::query()->whereIn('productId', $productIds)->get(['productId', 'branchId', 'quantity'])->groupBy('productId');
        $totals = Product::query()->whereIn('id', $productIds)->pluck('currentStock', 'id');

        $out = [];
        foreach ($productIds as $id) {
            $byBranch = ($rows[$id] ?? collect())->pluck('quantity', 'branchId')->map(fn ($qty) => (int) $qty);
            $qty = (int) ($byBranch[$branchId] ?? 0);
            if ($branchId === $main) {
                $qty += (int) ($totals[$id] ?? 0) - $byBranch->sum();
            }
            $out[$id] = $qty;
        }

        return $out;
    }

    /**
     * Adds stock to a branch (main when none given) and logs the movement.
     */
    public function add(Product $product, int $qty, ?string $branchId, ?string $type, array $log = []): void
    {
        if ($qty === 0) {
            return;
        }

        $this->apply($product, [$branchId ?: $this->mainBranchId($product->tenantId) => $qty], $type, $log);
    }

    /**
     * Takes stock from one branch, or when no branch is given from the main branch first then the fullest branches.
     */
    public function take(Product $product, int $qty, ?string $branchId, ?string $type, array $log = []): void
    {
        if ($qty <= 0) {
            return;
        }

        if ($branchId) {
            $this->apply($product, [$branchId => -$qty], $type, $log);

            return;
        }

        $main = $this->mainBranchId($product->tenantId);
        if (! $main) {
            $this->apply($product, ['' => -$qty], $type, $log);

            return;
        }

        $quantities = $this->quantities($product);
        uksort($quantities, fn ($a, $b) => ($b === $main) <=> ($a === $main) ?: $quantities[$b] <=> $quantities[$a]);

        $changes = [];
        $left = $qty;
        foreach ($quantities as $id => $available) {
            if ($left <= 0) {
                break;
            }
            $part = min($left, max(0, $available));
            if ($part > 0) {
                $changes[$id] = -$part;
                $left -= $part;
            }
        }
        if ($left > 0 && $main) {
            $changes[$main] = ($changes[$main] ?? 0) - $left;
        }

        $this->apply($product, $changes, $type, $log);
    }

    /**
     * Sets a branch's count to an exact figure (stock takes).
     */
    public function set(Product $product, int $qty, ?string $branchId, string $type, array $log = []): int
    {
        $branchId = $branchId ?: $this->mainBranchId($product->tenantId);
        $delta = $qty - $this->quantityAt($product, $branchId);
        if ($delta !== 0) {
            $this->apply($product, [$branchId => $delta], $type, $log);
        }

        return $delta;
    }

    public function transfer(Product $product, string $fromBranchId, string $toBranchId, int $qty, ?string $notes = null): void
    {
        if ($fromBranchId === $toBranchId) {
            throw ValidationException::withMessages(['toBranchId' => 'Pick a different branch to move stock to.']);
        }

        $available = $this->quantityAt($product, $fromBranchId);
        if ($qty < 1 || $qty > $available) {
            throw ValidationException::withMessages(['quantity' => "Only {$available} available at that branch."]);
        }

        $reference = 'TRF-'.strtoupper(substr((string) str()->ulid(), -8));
        $this->apply($product, [$fromBranchId => -$qty], 'TRANSFER_OUT', ['reference' => $reference, 'notes' => $notes]);
        $this->apply($product, [$toBranchId => $qty], 'TRANSFER_IN', ['reference' => $reference, 'notes' => $notes]);
    }

    /**
     * @param  array<string, int>  $changes  branchId => signed quantity
     * @param  array{reference?: ?string, notes?: ?string}  $log
     */
    private function apply(Product $product, array $changes, ?string $type, array $log): void
    {
        $run = function () use ($product, $changes, $type, $log) {
            $product->forceFill(['currentStock' => (int) Product::query()->whereKey($product->id)->value('currentStock')])
                ->syncOriginalAttribute('currentStock');

            if (! $this->mainBranchId($product->tenantId)) {
                $before = (int) $product->currentStock;
                $delta = array_sum($changes);
                $product->forceFill(['currentStock' => $before + $delta])->save();
                $this->logMovement($product, null, $type, $delta, $before, $before + $delta, $log);

                return;
            }

            $before = $this->quantities($product);
            $after = $before;
            foreach ($changes as $branchId => $delta) {
                if ($branchId !== '' && $delta !== 0) {
                    $after[$branchId] = ($after[$branchId] ?? 0) + $delta;
                }
            }

            $stored = BranchStock::query()->where('productId', $product->id)->pluck('quantity', 'branchId');
            foreach ($after as $branchId => $qty) {
                if (! $stored->has($branchId) || (int) $stored[$branchId] !== $qty) {
                    BranchStock::query()->updateOrCreate(
                        ['branchId' => $branchId, 'productId' => $product->id],
                        ['tenantId' => $product->tenantId, 'quantity' => $qty],
                    );
                }
            }

            foreach ($changes as $branchId => $delta) {
                if ($branchId !== '' && $delta !== 0) {
                    $this->logMovement($product, $branchId, $type, $delta, (int) ($before[$branchId] ?? 0), $after[$branchId], $log);
                }
            }

            $product->forceFill(['currentStock' => array_sum($after)])->save();
        };

        DB::transactionLevel() > 0 ? $run() : DB::transaction($run);
    }

    private function logMovement(Product $product, ?string $branchId, ?string $type, int $delta, int $before, int $after, array $log): void
    {
        if (! $type || $delta === 0) {
            return;
        }

        StockMovement::query()->create([
            'productId' => $product->id,
            'tenantId' => $product->tenantId,
            'branchId' => $branchId,
            'type' => $type,
            'quantity' => $delta,
            'beforeQty' => $before,
            'afterQty' => $after,
            'reference' => $log['reference'] ?? null,
            'notes' => $log['notes'] ?? null,
            'createdAt' => now(),
        ]);
    }
}
