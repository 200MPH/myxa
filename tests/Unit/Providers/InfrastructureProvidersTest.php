<?php

declare(strict_types=1);

namespace Test\Unit\Providers;

use App\Config\ConfigRepository;
use App\Auth\SessionStoreInterface;
use App\Auth\Stores\DatabaseSessionStore;
use App\Auth\Stores\FileSessionStore;
use App\Auth\Stores\RedisSessionStore;
use App\RateLimit\RedisRateLimiterStore;
use App\Providers\CacheServiceProvider;
use App\Providers\ConfigServiceProvider;
use App\Providers\DatabaseServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\FrameworkServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\RateLimitServiceProvider;
use App\Providers\RedisServiceProvider;
use App\Providers\RoutesServiceProvider;
use App\Providers\StorageServiceProvider;
use App\Support\Facades\Config;
use Myxa\Auth\AuthManager;
use Myxa\Auth\BearerTokenResolverInterface;
use Myxa\Auth\SessionGuard;
use Myxa\Application;
use Myxa\Cache\CacheManager;
use Myxa\Container\Exceptions\NotFoundException;
use Myxa\Database\DatabaseManager;
use Myxa\Database\Model\Model;
use Myxa\Events\EventBusInterface;
use Myxa\Http\Request;
use Myxa\Http\Response;
use Myxa\RateLimit\RateLimiter;
use Myxa\RateLimit\RateLimiterStoreInterface;
use Myxa\Redis\RedisManager;
use Myxa\Redis\Connection\InMemoryRedisStore;
use Myxa\Redis\Connection\RedisConnection;
use Myxa\Routing\Router;
use Myxa\Storage\StorageManager;
use Myxa\Support\Facades\DB;
use Myxa\Support\Facades\Redis;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionProperty;
use Test\TestCase;

#[CoversClass(CacheServiceProvider::class)]
#[CoversClass(ConfigServiceProvider::class)]
#[CoversClass(DatabaseServiceProvider::class)]
#[CoversClass(EventServiceProvider::class)]
#[CoversClass(FrameworkServiceProvider::class)]
#[CoversClass(AuthServiceProvider::class)]
#[CoversClass(RateLimitServiceProvider::class)]
#[CoversClass(RedisServiceProvider::class)]
#[CoversClass(RoutesServiceProvider::class)]
#[CoversClass(StorageServiceProvider::class)]
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

    public function testCacheProviderRegistersConfiguredRedisStore(): void
    {
        $app = new Application();
        $app->instance(ConfigRepository::class, new ConfigRepository([
            'cache' => [
                'default' => 'redis',
                'stores' => [
                    'redis' => [
                        'driver' => 'redis',
                        'connection' => 'cache',
                        'prefix' => 'cache:test:',
                    ],
                ],
            ],
        ]));
        $app->instance(
            RedisManager::class,
            new RedisManager('cache', new RedisConnection(new InMemoryRedisStore())),
        );

        $app->register(CacheServiceProvider::class);
        $app->boot();

        $manager = $app->make(CacheManager::class);
        $manager->put('framework:redis-ping', ['ok' => true]);

        self::assertSame('redis', $manager->getDefaultStore());
        self::assertSame(['ok' => true], $manager->get('framework:redis-ping'));
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

    public function testDatabaseProviderDirectRegisterAndBootExposeFacadeManager(): void
    {
        $app = new Application();
        $app->instance(ConfigRepository::class, new ConfigRepository([
            'database' => [
                'default' => 'analytics',
                'connections' => [
                    'analytics' => [
                        'driver' => 'mysql',
                        'host' => 'db',
                        'database' => 'myxa',
                    ],
                ],
            ],
        ]));

        DB::clearManager();
        $provider = new DatabaseServiceProvider();
        $provider->setApplication($app);
        $provider->register();
        $app->boot();

        $manager = $app->make(DatabaseManager::class);

        self::assertInstanceOf(DatabaseManager::class, DB::getManager());
        self::assertSame('analytics', DB::getManager()->getDefaultConnection());
        self::assertTrue(DB::getManager()->hasConnection('analytics'));
        $modelManager = new ReflectionProperty(Model::class, 'sharedManager');
        $modelManager->setAccessible(true);
        self::assertSame($manager, $modelManager->getValue());
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

    public function testRedisProviderDirectRegisterAndBootExposeFacadeManager(): void
    {
        $app = new Application();
        $app->instance(ConfigRepository::class, new ConfigRepository([
            'services' => [
                'redis' => [
                    'default' => 'cache',
                    'connections' => [
                        'cache' => [
                            'host' => 'redis',
                        ],
                    ],
                ],
            ],
        ]));

        Redis::clearManager();
        $provider = new RedisServiceProvider();
        $provider->setApplication($app);
        $provider->register();
        $app->boot();

        $manager = $app->make(RedisManager::class);

        self::assertInstanceOf(RedisManager::class, Redis::getManager());
        self::assertTrue(Redis::getManager()->hasConnection('cache'));
        self::assertSame('cache', $manager->getDefaultConnection());
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

    public function testAuthProviderDirectRegisterCreatesAuthManagersAndFileSessionStore(): void
    {
        $app = new Application();
        $app->instance(ConfigRepository::class, new ConfigRepository([
            'auth' => [
                'session' => [
                    'driver' => 'file',
                    'path' => storage_path('framework/testing/provider-auth-sessions'),
                    'cookie' => 'auth_session',
                ],
                'tokens' => [
                    'length' => 40,
                    'default_name' => 'cli',
                    'default_scopes' => ['*'],
                ],
            ],
        ]));

        $provider = new AuthServiceProvider();
        $provider->setApplication($app);
        $provider->register();

        self::assertInstanceOf(AuthManager::class, $app->make(AuthManager::class));
        self::assertInstanceOf(SessionGuard::class, $app->make(SessionGuard::class));
        self::assertInstanceOf(BearerTokenResolverInterface::class, $app->make(BearerTokenResolverInterface::class));
        self::assertInstanceOf(SessionStoreInterface::class, $app->make(SessionStoreInterface::class));
        self::assertInstanceOf(FileSessionStore::class, $app->make(SessionStoreInterface::class));
        self::assertSame($app->make(AuthManager::class), $app->make('auth'));
    }

    public function testAuthProviderRejectsUnsupportedSessionDrivers(): void
    {
        $app = new Application();
        $app->instance(ConfigRepository::class, new ConfigRepository([
            'auth' => [
                'session' => [
                    'driver' => 'unsupported',
                ],
            ],
        ]));

        $provider = new AuthServiceProvider();
        $provider->setApplication($app);
        $provider->register();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsupported auth session driver [unsupported].');

        $app->make(SessionStoreInterface::class);
    }

    public function testAuthProviderCanResolveDatabaseAndRedisSessionStores(): void
    {
        $databaseApp = new Application();
        $databaseApp->instance(ConfigRepository::class, new ConfigRepository([
            'auth' => [
                'session' => [
                    'driver' => 'database',
                ],
            ],
        ]));

        $databaseProvider = new AuthServiceProvider();
        $databaseProvider->setApplication($databaseApp);
        $databaseProvider->register();

        self::assertInstanceOf(DatabaseSessionStore::class, $databaseApp->make(SessionStoreInterface::class));

        $redisApp = new Application();
        $redisApp->instance(ConfigRepository::class, new ConfigRepository([
            'auth' => [
                'session' => [
                    'driver' => 'redis',
                    'redis' => [
                        'connection' => 'sessions',
                        'prefix' => 'auth-session:',
                    ],
                ],
            ],
        ]));
        $redisApp->instance(
            RedisManager::class,
            new RedisManager('sessions', new RedisConnection(new InMemoryRedisStore())),
        );

        $redisProvider = new AuthServiceProvider();
        $redisProvider->setApplication($redisApp);
        $redisProvider->register();

        self::assertInstanceOf(RedisSessionStore::class, $redisApp->make(SessionStoreInterface::class));
    }

    public function testRateLimitProviderDirectRegisterCreatesRedisBackedLimiter(): void
    {
        $app = new Application();
        $app->instance(ConfigRepository::class, new ConfigRepository([
            'rate_limit' => [
                'default_store' => 'redis',
                'stores' => [
                    'redis' => [
                        'driver' => 'redis',
                        'connection' => 'cache',
                        'prefix' => 'test-rate:',
                    ],
                ],
            ],
        ]));
        $app->instance(RedisManager::class, new RedisManager('cache', new RedisConnection(new InMemoryRedisStore())));

        $provider = new RateLimitServiceProvider();
        $provider->setApplication($app);
        $provider->register();

        self::assertInstanceOf(RateLimiter::class, $app->make(RateLimiter::class));
        self::assertInstanceOf(RateLimiterStoreInterface::class, $app->make(RateLimiterStoreInterface::class));
        self::assertInstanceOf(RedisRateLimiterStore::class, $app->make(RateLimiterStoreInterface::class));
        self::assertSame($app->make(RateLimiter::class), $app->make('rate.limiter'));
    }

    public function testEventProviderExposesAnEmptyListenerMapByDefault(): void
    {
        $provider = new EventServiceProvider();
        $listeners = new \ReflectionMethod(EventServiceProvider::class, 'listeners');
        $listeners->setAccessible(true);

        self::assertSame([], $listeners->invoke($provider));
    }

    public function testStorageProviderRegistersConfiguredLocalDisks(): void
    {
        $localRoot = storage_path('framework/testing/storage-provider-' . uniqid('', true) . '/local');
        $publicRoot = storage_path('framework/testing/storage-provider-' . uniqid('', true) . '/public');

        $app = new Application();
        $app->instance(ConfigRepository::class, new ConfigRepository([
            'storage' => [
                'default' => 'local',
                'disks' => [
                    'local' => [
                        'driver' => 'local',
                        'root' => $localRoot,
                    ],
                    'public' => [
                        'driver' => 'local',
                        'root' => $publicRoot,
                    ],
                ],
            ],
        ]));

        try {
            $app->register(StorageServiceProvider::class);
            $app->boot();

            $manager = $app->make(StorageManager::class);
            $stored = $manager->put('avatars/jane.txt', 'hello-storage');

            self::assertSame('local', $manager->getDefaultStorage());
            self::assertTrue($manager->hasStorage('public'));
            self::assertSame('local', $stored->storage());
            self::assertSame('hello-storage', $manager->read('avatars/jane.txt'));
            self::assertSame($manager, $app->make('storage'));
        } finally {
            $this->removeDirectory(dirname($localRoot));
            $this->removeDirectory(dirname($publicRoot));
        }
    }

    public function testStorageProviderRegistersDatabaseBackedDiskWhenConfigured(): void
    {
        $app = new Application();
        $app->instance(ConfigRepository::class, new ConfigRepository([
            'storage' => [
                'default' => 'db',
                'disks' => [
                    'db' => [
                        'driver' => 'database',
                        'file_table' => 'files_meta',
                        'content_table' => 'files_blob',
                    ],
                ],
            ],
        ]));
        $app->instance(DatabaseManager::class, new DatabaseManager('testing'));

        $app->register(StorageServiceProvider::class);
        $app->boot();

        $manager = $app->make(StorageManager::class);
        $disk = $manager->storage('db');

        self::assertSame('db', $manager->getDefaultStorage());
        self::assertSame('db', $disk->alias());
    }

    public function testStorageProviderSkipsBindingWhenNoSupportedDisksExist(): void
    {
        $app = new Application();
        $app->instance(ConfigRepository::class, new ConfigRepository([
            'storage' => [
                'disks' => [
                    'broken' => [
                        'driver' => 'local',
                        'root' => '',
                    ],
                    'unknown' => [
                        'driver' => 's3',
                    ],
                ],
            ],
        ]));

        $app->register(StorageServiceProvider::class);
        $app->boot();

        $this->expectException(NotFoundException::class);
        $app->make('storage');
    }

    public function testRateLimitProviderRegistersConfiguredFileStore(): void
    {
        $rateLimitPath = storage_path('framework/testing/rate-limit-provider-' . uniqid('', true));

        $app = new Application();
        $app->instance(ConfigRepository::class, new ConfigRepository([
            'rate_limit' => [
                'default_store' => 'file',
                'stores' => [
                    'file' => [
                        'driver' => 'file',
                        'path' => $rateLimitPath,
                    ],
                ],
            ],
        ]));

        try {
            $app->register(RateLimitServiceProvider::class);
            $app->boot();

            $limiter = $app->make(RateLimiter::class);
            $result = $limiter->consume('provider:test', 3, 60);

            self::assertSame(1, $result->attempts);
            self::assertFileExists($rateLimitPath . '/' . sha1('provider:test') . '.json');
            self::assertSame($limiter, $app->make('rate.limiter'));
        } finally {
            $this->removeDirectory($rateLimitPath);
        }
    }

    public function testRateLimitProviderRegistersConfiguredRedisStore(): void
    {
        $app = new Application();
        $app->instance(ConfigRepository::class, new ConfigRepository([
            'services' => [
                'redis' => [
                    'default' => 'cache',
                ],
            ],
            'rate_limit' => [
                'default_store' => 'redis',
                'stores' => [
                    'redis' => [
                        'driver' => 'redis',
                        'connection' => 'cache',
                        'prefix' => 'rl:',
                    ],
                ],
            ],
        ]));
        $app->instance(RedisManager::class, new RedisManager(
            'cache',
            new RedisConnection(new InMemoryRedisStore()),
        ));

        $app->register(RateLimitServiceProvider::class);
        $app->boot();

        $limiter = $app->make(RateLimiter::class);
        $result = $limiter->consume('provider:redis', 3, 60);

        self::assertSame(1, $result->attempts);
        self::assertSame($limiter, $app->make('rate.limiter'));
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

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $child = $path . '/' . $entry;

            if (is_dir($child)) {
                $this->removeDirectory($child);
                continue;
            }

            unlink($child);
        }

        rmdir($path);
    }
}
