<?php

declare(strict_types=1);

namespace App\Cache\Store;

use Myxa\Cache\CacheStoreInterface;

final class NullCacheStore implements CacheStoreInterface
{
    public function get(string $key, mixed $default = null): mixed
    {
        return $default;
    }

    public function put(string $key, mixed $value, ?int $ttl = null): bool
    {
        return false;
    }

    public function forget(string $key): bool
    {
        return false;
    }

    public function has(string $key): bool
    {
        return false;
    }

    public function clear(): bool
    {
        return true;
    }
}
