<?php

declare(strict_types=1);

namespace App\Providers;

use App\Config\ConfigRepository;
use Myxa\Cache\CacheServiceProvider as FrameworkCacheServiceProvider;
use Myxa\Cache\Store\FileCacheStore;
use Myxa\Cache\Store\RedisCacheStore;
use Myxa\Redis\RedisManager;
use Myxa\Support\ServiceProvider;

final class CacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $app = $this->app();
        $config = $this->app()->make(ConfigRepository::class);
        $stores = [];

        foreach ($config->get('cache.stores', []) as $alias => $storeConfiguration) {
            if (!is_array($storeConfiguration)) {
                continue;
            }

            $driver = (string) ($storeConfiguration['driver'] ?? 'file');

            if ($driver === 'file') {
                $path = (string) ($storeConfiguration['path'] ?? '');
                if ($path === '') {
                    continue;
                }

                $stores[(string) $alias] = new FileCacheStore($path);

                continue;
            }

            if ($driver === 'redis') {
                $connection = (string) ($storeConfiguration['connection'] ?? '');
                $prefix = (string) ($storeConfiguration['prefix'] ?? 'cache:');

                if ($connection === '') {
                    continue;
                }

                $stores[(string) $alias] = static fn (): RedisCacheStore => new RedisCacheStore(
                    $app->make(RedisManager::class)->connection($connection),
                    $prefix,
                );
            }
        }

        $defaultStore = (string) $config->get('cache.default', array_key_first($stores) ?? 'local');
        $defaultPath = storage_path('cache');

        $defaultStoreConfiguration = $config->get(sprintf('cache.stores.%s', $defaultStore), []);
        if (is_array($defaultStoreConfiguration) && is_string($defaultStoreConfiguration['path'] ?? null)) {
            $defaultPath = $defaultStoreConfiguration['path'];
        }

        $this->app()->register(new FrameworkCacheServiceProvider($stores, $defaultStore, $defaultPath));
    }
}
