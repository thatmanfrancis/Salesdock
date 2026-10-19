<?php

namespace App\Support;

/**
 * Parses CSV uploads for the platform catalogue (name, brand, category, unit, barcode, description).
 */
class CatalogueCsv
{
    private const HEADERS = [
        'name' => ['name', 'product name', 'title', 'product', 'item'],
        'brand' => ['brand', 'manufacturer'],
        'category' => ['category', 'type', 'dept', 'department'],
        'unit' => ['unit', 'uom', 'unit of measure', 'pack'],
        'barcode' => ['barcode', 'upc', 'ean', 'bar code'],
        'description' => ['description', 'details', 'notes'],
    ];

    /**
     * @return list<array{name: ?string, brand: ?string, category: ?string, unit: ?string, barcode: ?string, description: ?string}>
     */
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

    /**
     * @return array{name: ?string, brand: ?string, category: ?string, unit: ?string, barcode: ?string, description: ?string}
     */
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

    /**
     * @return list<list<string>>
     */
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

        return array_map(fn ($line) => str_getcsv($line, ',', '"', '\\'), $lines);
    }

    private static function key(string $header): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower(trim($header))) ?? '';
    }
}
