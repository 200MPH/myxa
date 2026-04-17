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
use RuntimeException;

final class RateLimitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app()->singleton(
            RateLimiterStoreInterface::class,
            static function (Application $app): RateLimiterStoreInterface {
                $config = $app->make(ConfigRepository::class);
                $defaultStore = (string) $config->get('rate_limit.default_store');
                $storeConfiguration = $config->get(sprintf('rate_limit.stores.%s', $defaultStore));

                if ($defaultStore === '') {
                    throw new RuntimeException('Rate limit default_store must be configured.');
                }

                if (!is_array($storeConfiguration)) {
                    throw new RuntimeException(sprintf('Rate limit store [%s] is not configured.', $defaultStore));
                }

                $driver = (string) ($storeConfiguration['driver'] ?? '');

                return match ($driver) {
                    'file' => new FileRateLimiterStore((string) $storeConfiguration['path']),
                    'redis' => new RedisRateLimiterStore(
                        $app->make(RedisManager::class),
                        (string) $storeConfiguration['connection'],
                        (string) $storeConfiguration['prefix'],
                    ),
                    default => throw new RuntimeException(sprintf(
                        'Unsupported rate limit store driver [%s] for store [%s].',
                        $driver,
                        $defaultStore,
                    )),
                };
            },
        );

        $this->app()->register(FrameworkRateLimitServiceProvider::class);
    }
}
