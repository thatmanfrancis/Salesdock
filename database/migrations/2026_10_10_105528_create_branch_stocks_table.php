<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_stocks', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenantId');
            $table->string('branchId');
            $table->string('productId');
            $table->integer('quantity')->default(0);
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent();
            $table->unique(['branchId', 'productId']);
            $table->index(['tenantId']);
            $table->index(['productId']);
            $table->foreign('tenantId')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('branchId')->references('id')->on('branches')->cascadeOnDelete();
            $table->foreign('productId')->references('id')->on('products')->cascadeOnDelete();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->string('branchId')->nullable();
            $table->index(['branchId']);
        });

        DB::statement('ALTER TYPE "StockMovementType" ADD VALUE IF NOT EXISTS \'TRANSFER_IN\'');
        DB::statement('ALTER TYPE "StockMovementType" ADD VALUE IF NOT EXISTS \'TRANSFER_OUT\'');

        $mainBranches = DB::table('branches')
            ->orderByDesc('isMain')
            ->orderBy('createdAt')
            ->get(['id', 'tenantId'])
            ->unique('tenantId')
            ->pluck('id', 'tenantId');

        DB::table('products')->orderBy('id')->select(['id', 'tenantId', 'currentStock'])->chunk(500, function ($products) use ($mainBranches) {
            $rows = [];
            foreach ($products as $product) {
                $branchId = $mainBranches[$product->tenantId] ?? null;
                if (! $branchId) {
                    continue;
                }
                $rows[] = [
                    'id' => (string) Str::ulid(),
                    'tenantId' => $product->tenantId,
                    'branchId' => $branchId,
                    'productId' => $product->id,
                    'quantity' => (int) $product->currentStock,
                    'createdAt' => now(),
                    'updatedAt' => now(),
                ];
            }
            if ($rows !== []) {
                DB::table('branch_stocks')->insert($rows);
            }
        });

        DB::table('stock_movements')
            ->whereNull('branchId')
            ->update(['branchId' => DB::raw('(select bs."branchId" from branch_stocks bs where bs."productId" = stock_movements."productId" limit 1)')]);
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex(['branchId']);
            $table->dropColumn('branchId');
        });

        Schema::dropIfExists('branch_stocks');
    }
};
