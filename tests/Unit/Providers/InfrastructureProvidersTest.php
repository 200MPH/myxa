<?php

declare(strict_types=1);

namespace Test\Unit\Providers;

use App\Config\ConfigRepository;
use App\Providers\CacheServiceProvider;
use App\Providers\ConfigServiceProvider;
use App\Providers\DatabaseServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\FrameworkServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\RedisServiceProvider;
use App\Providers\RoutesServiceProvider;
use App\Support\Facades\Config;
use Myxa\Auth\AuthManager;
use Myxa\Auth\BearerTokenResolverInterface;
use Myxa\Auth\SessionGuard;
use Myxa\Application;
use Myxa\Cache\CacheManager;
use Myxa\Container\Exceptions\NotFoundException;
use Myxa\Database\DatabaseManager;
use Myxa\Events\EventBusInterface;
use Myxa\Http\Request;
use Myxa\Http\Response;
use Myxa\Redis\RedisManager;
use Myxa\Routing\Router;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionProperty;
use Test\TestCase;

#[CoversClass(CacheServiceProvider::class)]
#[CoversClass(ConfigServiceProvider::class)]
#[CoversClass(DatabaseServiceProvider::class)]
#[CoversClass(EventServiceProvider::class)]
#[CoversClass(FrameworkServiceProvider::class)]
#[CoversClass(AuthServiceProvider::class)]
#[CoversClass(RedisServiceProvider::class)]
#[CoversClass(RoutesServiceProvider::class)]
final class InfrastructureProvidersTest extends TestCase
{
    public function testFrameworkProviderRegistersCoreHttpServices(): void
    {
        $app = new Application();

        $app->register(FrameworkServiceProvider::class);
        $app->boot();

        self::assertInstanceOf(Request::class, $app->make(Request::class));
        self::assertInstanceOf(Response::class, $app->make(Response::class));
        self::assertInstanceOf(Router::class, $app->make(Router::class));
    }

    public function testConfigProviderInitializesFacadeDuringRegistration(): void
    {
        $app = new Application();
        $app->instance(ConfigRepository::class, new ConfigRepository([
            'app' => [
                'name' => 'Provider Config',
            ],
        ]));

        $app->register(ConfigServiceProvider::class);

        self::assertSame('Provider Config', Config::get('app.name'));
    }

    public function testCacheProviderRegistersConfiguredFileStore(): void
    {
        $cacheDirectory = storage_path('framework/testing/cache-provider-' . uniqid('', true));

        $app = new Application();
        $app->instance(ConfigRepository::class, new ConfigRepository([
            'cache' => [
                'default' => 'local',
                'stores' => [
                    'local' => [
                        'driver' => 'file',
                        'path' => $cacheDirectory,
                    ],
                ],
            ],
        ]));

        try {
            $app->register(CacheServiceProvider::class);
            $app->boot();

            $manager = $app->make(CacheManager::class);
            $manager->put('framework:ping', ['ok' => true]);

            self::assertSame('local', $manager->getDefaultStore());
            self::assertSame(['ok' => true], $manager->get('framework:ping'));
        } finally {
            foreach (glob($cacheDirectory . '/*.cache') ?: [] as $cacheFile) {
                if (is_file($cacheFile)) {
                    unlink($cacheFile);
                }
            }

            if (is_dir($cacheDirectory)) {
                rmdir($cacheDirectory);
            }
        }
    }

    public function testDatabaseProviderRegistersConfiguredConnections(): void
    {
        $app = new Application();
        $app->instance(ConfigRepository::class, new ConfigRepository([
            'database' => [
                'default' => 'analytics',
                'connections' => [
                    'analytics' => [
                        'driver' => 'mysql',
                        'host' => 'db',
                        'port' => 3306,
                        'database' => 'myxa',
                        'username' => 'root',
                        'password' => 'secret',
                    ],
                ],
            ],
        ]));

        $app->register(DatabaseServiceProvider::class);
        $app->boot();

        $manager = $app->make(DatabaseManager::class);

        self::assertSame('analytics', $manager->getDefaultConnection());
        self::assertTrue($manager->hasConnection('analytics'));
        self::assertSame($manager, $app->make('db'));
    }

    public function testDatabaseProviderSkipsBindingWhenNoValidConnectionsExist(): void
    {
        $app = new Application();
        $app->instance(ConfigRepository::class, new ConfigRepository([
            'database' => [
                'connections' => [
                    'broken' => [
                        'driver' => 'mysql',
                        'database' => '',
                        'host' => '',
                    ],
                ],
            ],
        ]));

        $app->register(DatabaseServiceProvider::class);
        $app->boot();

        $this->expectException(NotFoundException::class);
        $app->make('db');
    }

    public function testRedisProviderRegistersConfiguredConnections(): void
    {
        $app = new Application();
        $app->instance(ConfigRepository::class, new ConfigRepository([
            'services' => [
                'redis' => [
                    'default' => 'cache',
                    'connections' => [
                        'cache' => [
                            'host' => 'redis',
                            'port' => 6379,
                            'database' => 1,
                            'timeout' => 1.5,
                        ],
                    ],
                ],
            ],
        ]));

        $app->register(RedisServiceProvider::class);
        $app->boot();

        $manager = $app->make(RedisManager::class);

        self::assertSame('cache', $manager->getDefaultConnection());
        self::assertTrue($manager->hasConnection('cache'));
        self::assertSame($manager, $app->make('redis'));
    }

    public function testRoutesProviderLoadsRouteFilesFromRoutesDirectory(): void
    {
        $routeFile = route_path('zzz_test_' . uniqid('', true) . '.php');
        $routePath = '/__provider-test-' . uniqid('', true);

        $routeSource = <<<'PHP'
<?php

declare(strict_types=1);

use Myxa\Support\Facades\Route;

Route::get('%s', static fn (): string => 'loaded-route');
PHP;

        file_put_contents($routeFile, sprintf($routeSource, $routePath));

        $app = new Application();
        $app->instance(ConfigRepository::class, new ConfigRepository());

        try {
            $app->register(FrameworkServiceProvider::class);
            $app->register(RoutesServiceProvider::class);
            $app->boot();

            $result = $app->make(Router::class)->dispatch(new Request(server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => $routePath,
            ]));

            self::assertSame('loaded-route', $result);
        } finally {
            if (is_file($routeFile)) {
                unlink($routeFile);
            }
        }
    }

    public function testRoutesProviderLoadsCachedRoutesWhenEnabled(): void
    {
        $cachePath = storage_path('framework/testing/routes-provider-cache-' . uniqid('', true) . '.php');
        $cacheDirectory = dirname($cachePath);

        if (!is_dir($cacheDirectory)) {
            mkdir($cacheDirectory, 0777, true);
        }

        file_put_contents($cachePath, <<<'PHP'
<?php

declare(strict_types=1);

use Myxa\Support\Facades\Route;

Route::get('/__cached-provider-route', static fn (): string => 'cached-route');
PHP);

        $app = new Application();
        $app->instance(ConfigRepository::class, new ConfigRepository([
            'cache' => [
                'routes' => [
                    'enabled' => true,
                    'path' => $cachePath,
                ],
            ],
        ]));

        try {
            $app->register(FrameworkServiceProvider::class);
            $app->register(RoutesServiceProvider::class);
            $app->boot();

            $result = $app->make(Router::class)->dispatch(new Request(server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/__cached-provider-route',
            ]));

            self::assertSame('cached-route', $result);
        } finally {
            if (is_file($cachePath)) {
                unlink($cachePath);
            }

            if (is_dir($cacheDirectory) && (glob($cacheDirectory . '/*') ?: []) === []) {
                rmdir($cacheDirectory);
            }
        }
    }

    public function testEventProviderRegistersSharedEventBus(): void
    {
        $app = new Application();

        $app->register(EventServiceProvider::class);
        $app->boot();

        $bus = $app->make(EventBusInterface::class);

        self::assertInstanceOf(EventBusInterface::class, $bus);
        self::assertSame($bus, $app->make('events'));
    }

    public function testAuthProviderRegistersManagerResolversAndCustomSessionCookie(): void
    {
        $app = new Application();
        $app->instance(ConfigRepository::class, new ConfigRepository([
            'auth' => [
                'session' => [
                    'cookie' => 'custom_session',
                ],
            ],
        ]));

        $app->register(AuthServiceProvider::class);
        $app->boot();

        $manager = $app->make(AuthManager::class);
        $guard = $app->make(SessionGuard::class);
        $cookieProperty = new ReflectionProperty(SessionGuard::class, 'cookieName');

        self::assertInstanceOf(AuthManager::class, $manager);
        self::assertSame($manager, $app->make('auth'));
        self::assertInstanceOf(BearerTokenResolverInterface::class, $app->make(BearerTokenResolverInterface::class));
        self::assertSame('custom_session', $cookieProperty->getValue($guard));
    }
}
