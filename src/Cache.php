<?php
declare(strict_types=1);

namespace Elhoe;

/**
 * Tiny file cache (storage/cache/). Used for small ephemeral data.
 * For per-IP geo data we use the DB cache table (ip_geo_cache).
 */
final class Cache
{
    public static function set(string $key, $value, int $ttlSeconds = 3600): bool
    {
        $dir = STORAGE_PATH . '/cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $payload = [
            'expires_at' => time() + max(1, $ttlSeconds),
            'value'      => $value,
        ];
        $file = $dir . '/' . self::hash($key) . '.cache';
        return (bool) @file_put_contents($file, serialize($payload), LOCK_EX);
    }

    public static function get(string $key, $default = null)
    {
        $file = STORAGE_PATH . '/cache/' . self::hash($key) . '.cache';
        if (!is_file($file)) return $default;
        $raw = @file_get_contents($file);
        if ($raw === false) return $default;
        $payload = @unserialize($raw);
        if (!is_array($payload) || !isset($payload['expires_at'])) return $default;
        if ($payload['expires_at'] < time()) {
            @unlink($file);
            return $default;
        }
        return $payload['value'] ?? $default;
    }

    public static function forget(string $key): void
    {
        $file = STORAGE_PATH . '/cache/' . self::hash($key) . '.cache';
        if (is_file($file)) @unlink($file);
    }

    private static function hash(string $key): string
    {
        return sha1($key);
    }
}
