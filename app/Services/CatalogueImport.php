<?php

namespace App\Services;

use App\Models\CatalogueProduct;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CatalogueImport
{
    /**
     * @param  list<array<string, ?string>>  $rows
     * @return array{saved: int, skipped: int, skuAdded: int}
     */
    public function import(array $rows, string $createdBy): array
    {
        foreach ($rows as $index => $row) {
            if (trim((string) ($row['name'] ?? '')) === '' || trim((string) ($row['category'] ?? '')) === '') {
                throw ValidationException::withMessages(['file' => 'CSV row '.($index + 2).' needs both a name and a category.']);
            }
        }

        try {
            return Cache::lock('catalogue-csv-import', 120)->block(5, fn (): array => DB::transaction(function () use ($rows, $createdBy): array {
                $existing = CatalogueProduct::query()->get(['id', 'name', 'category', 'sku']);
                $byName = [];
                $bySku = [];
                foreach ($existing as $item) {
                    $byName[$this->identity($item->name, (string) $item->category)] = $item;
                    if ($item->sku !== null) {
                        $bySku[mb_strtolower($item->sku)] = true;
                    }
                }

                $saved = 0;
                $skipped = 0;
                $skuAdded = 0;
                $timestamp = now();
                foreach (array_chunk($rows, 200) as $chunk) {
                    $inserts = [];
                    $skuUpdates = [];
                    foreach ($chunk as $row) {
                        $name = trim((string) $row['name']);
                        $category = trim((string) $row['category']);
                        $sku = trim((string) ($row['sku'] ?? ''));
                        $sku = $sku !== '' ? $sku : null;
                        $identity = $this->identity($name, $category);
                        if (($sku !== null && isset($bySku[mb_strtolower($sku)])) || array_key_exists($identity, $byName)) {
                            $item = $byName[$identity] ?? null;
                            if ($item instanceof CatalogueProduct && ($item->sku === null || $item->sku === '') && $sku !== null && ! isset($bySku[mb_strtolower($sku)])) {
                                $skuUpdates[] = [
                                    'id' => $item->id,
                                    'name' => $item->name,
                                    'category' => $item->category,
                                    'sku' => $sku,
                                    'createdBy' => $createdBy,
                                    'createdAt' => $timestamp,
                                    'updatedAt' => $timestamp,
                                ];
                                $item->sku = $sku;
                                $bySku[mb_strtolower($sku)] = true;
                                $skuAdded++;
                            }
                            $skipped++;

                            continue;
                        }

                        $inserts[] = [
                            'id' => (string) str()->ulid(),
                            'name' => $name,
                            'category' => $category,
                            'sku' => $sku,
                            'brand' => $row['brand'] ?? null,
                            'unit' => $row['unit'] ?? null,
                            'barcode' => $row['barcode'] ?? null,
                            'description' => $row['description'] ?? null,
                            'isActive' => true,
                            'createdBy' => $createdBy,
                            'createdAt' => $timestamp,
                            'updatedAt' => $timestamp,
                        ];
                        $byName[$identity] = null;
                        if ($sku !== null) {
                            $bySku[mb_strtolower($sku)] = true;
                        }
                    }
                    if ($inserts !== []) {
                        CatalogueProduct::query()->insert($inserts);
                        $saved += count($inserts);
                    }
                    if ($skuUpdates !== []) {
                        CatalogueProduct::query()->upsert($skuUpdates, ['id'], ['sku', 'updatedAt']);
                    }
                }

                return compact('saved', 'skipped', 'skuAdded');
            }));
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages(['file' => 'Another catalogue upload is running. Please try again shortly.']);
        }
    }

    private function identity(string $name, string $category): string
    {
        return json_encode([mb_strtolower(trim($name)), mb_strtolower(trim($category))], JSON_THROW_ON_ERROR);
    }
}
