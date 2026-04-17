<?php

declare(strict_types=1);

namespace App\Providers;

use App\Config\ConfigRepository;
use App\RateLimit\RedisRateLimiterStore;
use Myxa\Application;
use Myxa\RateLimit\FileRateLimiterStore;
use Myxa\RateLimit\RateLimitServiceProvider as FrameworkRateLimitServiceProvider;
use Myxa\RateLimit\RateLimiterStoreInterface;
use Myxa\Redis\RedisManager;
use Myxa\Support\ServiceProvider;

final class RateLimitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app()->singleton(
            RateLimiterStoreInterface::class,
            static function (Application $app): RateLimiterStoreInterface {
                $config = $app->make(ConfigRepository::class);
                $defaultStore = (string) $config->get('rate_limit.default_store', 'file');
                $storeConfiguration = $config->get(sprintf('rate_limit.stores.%s', $defaultStore), []);

                if (!is_array($storeConfiguration)) {
                    $storeConfiguration = [];
                }

                $driver = (string) ($storeConfiguration['driver'] ?? 'file');
                $path = (string) ($storeConfiguration['path'] ?? storage_path('rate-limit'));
                $connection = (string) ($storeConfiguration['connection'] ?? $config->get('services.redis.default', 'cache'));
                $prefix = (string) ($storeConfiguration['prefix'] ?? 'rate-limit:');

                return match ($driver) {
                    'file' => new FileRateLimiterStore($path),
                    'redis' => new RedisRateLimiterStore(
                        $app->make(RedisManager::class),
                        $connection !== '' ? $connection : null,
                        $prefix !== '' ? $prefix : 'rate-limit:',
                    ),
                    default => new FileRateLimiterStore(storage_path('rate-limit')),
                };
            },
        );

        $this->app()->register(FrameworkRateLimitServiceProvider::class);
    }
}
