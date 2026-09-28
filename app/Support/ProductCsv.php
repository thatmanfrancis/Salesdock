<?php

namespace App\Support;

class ProductCsv
{
    private const HEADERS = [
        'name' => ['name', 'product name', 'title', 'product'],
        'category' => ['category', 'type', 'dept', 'department'],
        'price' => ['price', 'selling price', 'rate', 'retail price', 'retail'],
        'sku' => ['sku', 'code', 'item code', 'product code'],
        'barcode' => ['barcode', 'upc', 'ean', 'bar code'],
        'costPrice' => ['costprice', 'cost price', 'cost', 'unit cost', 'purchase price'],
        'wholesalePrice' => ['wholesaleprice', 'wholesale price', 'wholesale'],
        'currentStock' => ['currentstock', 'current stock', 'stock', 'qty', 'quantity', 'opening stock', 'inventory', 'on hand', 'units'],
        'minThreshold' => ['minthreshold', 'min threshold', 'low stock threshold', 'threshold', 'min'],
        'expiresAt' => ['expiresat', 'expires at', 'expiry', 'expiry date', 'expiration date'],
    ];

    public static function parse(string $text): array
    {
        $rows = self::lines($text);
        if ($rows === []) {
            return [];
        }

        $headers = array_map([self::class, 'key'], array_shift($rows));
        $parsed = [];
        foreach ($rows as $line) {
            if (count(array_filter($line, fn ($cell) => trim((string) $cell) !== '')) === 0) {
                continue;
            }
            $record = [];
            foreach ($headers as $index => $header) {
                $record[$header] = trim((string) ($line[$index] ?? ''));
            }
            $parsed[] = self::map($record);
        }

        return $parsed;
    }

    private static function map(array $row): array
    {
        $mapped = [];
        foreach (self::HEADERS as $field => $aliases) {
            $mapped[$field] = null;
            foreach ($aliases as $alias) {
                if (($row[self::key($alias)] ?? '') !== '') {
                    $mapped[$field] = $row[self::key($alias)];
                    break;
                }
            }
        }

        return $mapped;
    }

    private static function lines(string $text): array
    {
        $lines = [];
        $current = '';
        $quoted = false;
        $length = strlen($text);
        for ($i = 0; $i < $length; $i++) {
            $char = $text[$i];
            if ($char === '"') {
                $quoted = ! $quoted;
            } elseif ($char === "\n" && ! $quoted) {
                $lines[] = $current;
                $current = '';
            } elseif ($char !== "\r") {
                $current .= $char;
            }
        }
        if (trim($current) !== '') {
            $lines[] = $current;
        }

        return array_values(array_filter(array_map(fn ($line) => str_getcsv($line), $lines), fn ($line) => $line !== [null]));
    }

    private static function key(string $value): string
    {
        return preg_replace('/\s+/', ' ', strtolower(trim(str_replace(['_', '-'], ' ', $value)))) ?? '';
    }
}
