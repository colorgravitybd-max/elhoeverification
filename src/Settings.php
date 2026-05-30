<?php
declare(strict_types=1);

namespace Elhoe;

/**
 * Settings repository (key-value rows in `settings` table).
 * Used for editable runtime values (brand colors, pixel id, etc.).
 */
final class Settings
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache !== null) return self::$cache;
        $rows = Database::all("SELECT key_name, value FROM settings");
        self::$cache = [];
        foreach ($rows as $r) {
            self::$cache[$r['key_name']] = $r['value'];
        }
        return self::$cache;
    }

    public static function get(string $key, $default = ''): string
    {
        $all = self::all();
        $v = $all[$key] ?? null;
        if ($v === null || $v === '') {
            // Accept any scalar default (string, int, float, bool) and coerce to string.
            return $default === null ? '' : (is_scalar($default) ? (string) $default : '');
        }
        return (string) $v;
    }

    public static function set(string $key, string $value): void
    {
        Database::exec(
            "INSERT INTO settings (key_name, value) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE value = VALUES(value)",
            [':k' => $key, ':v' => $value]
        );
        if (self::$cache !== null) {
            self::$cache[$key] = $value;
        }
    }

    public static function setMany(array $kv): void
    {
        foreach ($kv as $k => $v) {
            self::set((string) $k, (string) $v);
        }
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
