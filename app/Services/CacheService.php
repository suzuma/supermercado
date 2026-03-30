<?php
declare(strict_types=1);

namespace App\Services;

use Core\Log;

class CacheService
{
    private static function filePath(string $key): string
    {
        return _CACHE_PATH_ . 'qcache_' . md5($key) . '.json';
    }

    public static function get(string $key): mixed
    {
        $path = self::filePath($key);

        if (!file_exists($path)) {
            return null;
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        $entry = json_decode($raw, true);
        if (!is_array($entry) || $entry['expires'] < time()) {
            @unlink($path);
            return null;
        }

        return $entry['data'];
    }

    public static function set(string $key, mixed $value, int $ttlSeconds = 300): void
    {
        $path  = self::filePath($key);
        $entry = json_encode(['expires' => time() + $ttlSeconds, 'data' => $value]);

        if ($entry === false) {
            return;
        }

        @file_put_contents($path, $entry, LOCK_EX);
    }

    public static function remember(string $key, int $ttlSeconds, callable $callback): mixed
    {
        $cached = self::get($key);
        if ($cached !== null) {
            return $cached;
        }

        $value = $callback();
        self::set($key, $value, $ttlSeconds);
        return $value;
    }

    public static function forget(string $key): void
    {
        $path = self::filePath($key);
        if (file_exists($path)) {
            @unlink($path);
        }
    }

    public static function flush(): void
    {
        try {
            foreach (glob(_CACHE_PATH_ . 'qcache_*.json') ?: [] as $file) {
                @unlink($file);
            }
        } catch (\Exception $e) {
            Log::error(self::class, $e->getMessage());
        }
    }
}
