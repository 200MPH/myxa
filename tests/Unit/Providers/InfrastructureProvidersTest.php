<?php

declare(strict_types=1);

namespace Test\Unit\Providers;

use App\Config\ConfigRepository;
use App\Providers\DatabaseServiceProvider;
use App\Providers\FrameworkServiceProvider;
use App\Providers\RedisServiceProvider;
use App\Providers\RoutesServiceProvider;
use Myxa\Application;
use Myxa\Container\Exceptions\NotFoundException;
use Myxa\Database\DatabaseManager;
use Myxa\Http\Request;
use Myxa\Http\Response;
use Myxa\Redis\RedisManager;
use Myxa\Routing\Router;
use PHPUnit\Framework\Attributes\CoversClass;
use Test\TestCase;

#[CoversClass(DatabaseServiceProvider::class)]
#[CoversClass(FrameworkServiceProvider::class)]
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
}
