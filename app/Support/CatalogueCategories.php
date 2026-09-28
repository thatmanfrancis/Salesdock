<?php

namespace App\Support;

class CatalogueCategories
{
    public static function extra(): array
    {
        if (! is_file(self::path())) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents(self::path()), true);
        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, fn ($name) => is_string($name) && trim($name) !== ''));
    }

    public static function remember(string $name): void
    {
        $name = trim($name);
        if ($name === '' || self::has($name)) {
            return;
        }
        $all = self::extra();
        $all[] = $name;
        self::write($all);
    }

    public static function rename(string $from, string $to): void
    {
        $all = array_map(function ($name) use ($from, $to) {
            return mb_strtolower($name) === mb_strtolower($from) ? $to : $name;
        }, self::extra());
        if (! self::has($to, $all)) {
            $all[] = $to;
        }
        self::write(array_values(array_unique($all)));
    }

    public static function forget(string $name): void
    {
        $all = array_values(array_filter(self::extra(), fn ($existing) => mb_strtolower($existing) !== mb_strtolower($name)));
        self::write($all);
    }

    public static function has(string $name, ?array $among = null): bool
    {
        foreach ($among ?? self::extra() as $existing) {
            if (mb_strtolower($existing) === mb_strtolower($name)) {
                return true;
            }
        }

        return false;
    }

    private static function path(): string
    {
        return storage_path('app/catalogue-categories.json');
    }

    private static function write(array $names): void
    {
        file_put_contents(self::path(), json_encode(array_values($names), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}
