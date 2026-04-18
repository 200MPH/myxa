<?php

declare(strict_types=1);

namespace App\Providers;

use App\Auth\AuthConfig;
use App\Auth\AuthInstallService;
use App\Auth\BearerTokenResolver;
use App\Auth\PasswordHasher;
use App\Auth\SessionStoreInterface;
use App\Auth\SessionManager;
use App\Auth\SessionUserResolver;
use App\Auth\Stores\DatabaseSessionStore;
use App\Auth\Stores\FileSessionStore;
use App\Auth\Stores\RedisSessionStore;
use App\Auth\TokenManager;
use App\Auth\UserManager;
use Myxa\Application;
use Myxa\Auth\AuthServiceProvider as FrameworkAuthServiceProvider;
use Myxa\Auth\BearerTokenResolverInterface;
use Myxa\Auth\SessionUserResolverInterface;
use Myxa\Auth\SessionGuard;
use Myxa\Redis\RedisManager;
use Myxa\Support\ServiceProvider;
use RuntimeException;

final class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register the app's auth managers, resolvers, and framework guard bindings.
     */
    public function register(): void
    {
        $this->app()->register(new FrameworkAuthServiceProvider());

        $this->app()->singleton(AuthConfig::class);
        $this->app()->singleton(PasswordHasher::class);
        $this->app()->singleton(UserManager::class);
        $this->app()->singleton(TokenManager::class);
        $this->app()->singleton(
            SessionStoreInterface::class,
            static function (Application $app): SessionStoreInterface {
                $config = $app->make(AuthConfig::class);

                return match ($config->sessionDriver()) {
                    'file' => new FileSessionStore($config->sessionPath()),
                    'database' => new DatabaseSessionStore(),
                    'redis' => new RedisSessionStore(
                        $app->make(RedisManager::class),
                        $config->sessionRedisConnection(),
                        $config->sessionRedisPrefix(),
                    ),
                    default => throw new RuntimeException(sprintf(
                        'Unsupported auth session driver [%s].',
                        $config->sessionDriver(),
                    )),
                };
            },
        );
        $this->app()->singleton(SessionManager::class);
        $this->app()->singleton(AuthInstallService::class);

        $this->app()->singleton(BearerTokenResolverInterface::class, BearerTokenResolver::class);
        $this->app()->singleton(SessionUserResolverInterface::class, SessionUserResolver::class);
        $this->app()->singleton(
            SessionGuard::class,
            static function (Application $app): SessionGuard {
                return new SessionGuard(
                    $app->make(SessionUserResolverInterface::class),
                    $app->make(AuthConfig::class)->sessionCookieName(),
                );
            },
        );
    }
}
