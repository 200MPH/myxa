<?php

declare(strict_types=1);

namespace App\Providers;

use App\Config\ConfigRepository;
use Myxa\Cache\CacheServiceProvider as FrameworkCacheServiceProvider;
use Myxa\Cache\Store\FileCacheStore;
use Myxa\Support\ServiceProvider;

final class CacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $config = $this->app()->make(ConfigRepository::class);
        $stores = [];

        foreach ($config->get('cache.stores', []) as $alias => $storeConfiguration) {
            if (!is_array($storeConfiguration)) {
                continue;
            }

            $driver = (string) ($storeConfiguration['driver'] ?? 'file');
            $path = (string) ($storeConfiguration['path'] ?? '');

            if ($driver !== 'file' || $path === '') {
                continue;
            }

            $stores[(string) $alias] = new FileCacheStore($path);
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
