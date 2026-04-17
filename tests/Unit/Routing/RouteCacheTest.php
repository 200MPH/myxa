<?php

declare(strict_types=1);

namespace Test\Unit\Routing;

use App\Config\ConfigRepository;
use App\Routing\RouteCache;
use Myxa\Application;
use Myxa\Routing\Router;
use Myxa\Support\Facades\Route;
use PHPUnit\Framework\Attributes\CoversClass;
use Test\TestCase;

#[CoversClass(RouteCache::class)]
final class RouteCacheTest extends TestCase
{
    private string $cachePath;

    /**
     * @var list<string>
     */
    private array $routeFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->cachePath = sys_get_temp_dir() . '/myxa-route-cache-' . uniqid('', true) . '.php';
    }

    protected function tearDown(): void
    {
        if (is_file($this->cachePath)) {
            unlink($this->cachePath);
        }

        foreach ($this->routeFiles as $routeFile) {
            if (is_file($routeFile)) {
                unlink($routeFile);
            }
        }

        parent::tearDown();
    }

    public function testCompileRejectsClosureHandlers(): void
    {
        $router = new Router(new Application());
        $router->get('/closure-route', static fn (): string => 'nope');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to cache route [/closure-route]');

        RouteCache::compile($router->routes());
    }

    public function testConfigurationHelpersExposeEnabledPathExistsAndClear(): void
    {
        $enabledConfig = new ConfigRepository([
            'cache' => [
                'routes' => [
                    'enabled' => true,
                    'path' => $this->cachePath,
                ],
            ],
        ]);
        $fallbackConfig = new ConfigRepository([
            'cache' => [
                'routes' => [
                    'enabled' => false,
                    'path' => '   ',
                ],
            ],
        ]);

        self::assertTrue(RouteCache::isEnabled($enabledConfig));
        self::assertFalse(RouteCache::exists($enabledConfig));
        self::assertSame($this->cachePath, RouteCache::path($enabledConfig));
        self::assertStringEndsWith('storage/cache/framework/routes.php', RouteCache::path($fallbackConfig));

        file_put_contents($this->cachePath, '<?php return true;');

        self::assertTrue(RouteCache::exists($enabledConfig));
        self::assertTrue(RouteCache::clear($enabledConfig));
        self::assertFalse(RouteCache::exists($enabledConfig));
        self::assertTrue(RouteCache::loadCachedRoutes($enabledConfig) === false);
    }

    public function testLoadCachedRoutesReturnsTrueWhenManifestExists(): void
    {
        $config = new ConfigRepository([
            'cache' => [
                'routes' => [
                    'path' => $this->cachePath,
                ],
            ],
        ]);

        file_put_contents($this->cachePath, <<<'PHP'
<?php

declare(strict_types=1);

$GLOBALS['myxa_route_cache_loaded'] = true;
PHP);

        unset($GLOBALS['myxa_route_cache_loaded']);

        self::assertTrue(RouteCache::loadCachedRoutes($config));
        self::assertTrue($GLOBALS['myxa_route_cache_loaded']);

        unset($GLOBALS['myxa_route_cache_loaded']);
    }

    public function testCompileIncludesMiddlewareDefinitions(): void
    {
        $router = new Router(new Application());
        $router->get('/users', ['UserController', 'index'])->middleware('auth', ['throttle', 'api']);

        $compiled = RouteCache::compile($router->routes());

        self::assertStringContainsString("Route::match(array (\n  0 => 'GET',\n), '/users'", $compiled);
        self::assertStringContainsString("\$route->middleware(...array (\n  0 => 'auth',", $compiled);
        self::assertStringContainsString("1 => 'throttle',", $compiled);
        self::assertStringContainsString("2 => 'api',", $compiled);
    }

    public function testCompileRejectsClosureMiddleware(): void
    {
        $router = new Router(new Application());
        $router->get('/closure-middleware', ['UserController', 'index'])
            ->middleware(static fn (): string => 'nope');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('middlewares contains a Closure');

        RouteCache::compile($router->routes());
    }

    public function testSourceFilesAreSortedAndLoadSourceRoutesRequiresThem(): void
    {
        $suffix = uniqid('', true);
        $lateFile = route_path('zzz_route_cache_' . $suffix . '.php');
        $earlyFile = route_path('aaa_route_cache_' . $suffix . '.php');
        $this->routeFiles = [$lateFile, $earlyFile];

        file_put_contents($lateFile, <<<'PHP'
<?php

declare(strict_types=1);

$GLOBALS['myxa_route_cache_source_files'][] = 'late';
PHP);
        file_put_contents($earlyFile, <<<'PHP'
<?php

declare(strict_types=1);

$GLOBALS['myxa_route_cache_source_files'][] = 'early';
PHP);

        unset($GLOBALS['myxa_route_cache_source_files']);

        $sourceFiles = RouteCache::sourceFiles();
        $ourFiles = array_values(array_filter(
            $sourceFiles,
            static fn (string $routeFile): bool => str_contains($routeFile, $suffix),
        ));

        self::assertSame([$earlyFile, $lateFile], $ourFiles);

        $app = new Application();
        $router = new Router($app);
        $app->instance(Router::class, $router);
        $app->instance('router', $router);
        Route::setRouter($router);

        RouteCache::loadSourceRoutes();

        self::assertSame(['early', 'late'], $GLOBALS['myxa_route_cache_source_files']);

        unset($GLOBALS['myxa_route_cache_source_files']);
    }

    public function testBuildFromSourceWritesCompiledManifestAndRestoresOriginalRouter(): void
    {
        $suffix = uniqid('', true);
        $routeFile = route_path('zzz_build_route_cache_' . $suffix . '.php');
        $routePath = '/__route-cache-build-' . $suffix;
        $this->routeFiles = [$routeFile];

        file_put_contents($routeFile, sprintf(<<<'PHP'
<?php

declare(strict_types=1);

use Myxa\Support\Facades\Route;

Route::get('%s', ['BuildCacheController', 'show'])->middleware('auth');
PHP, $routePath));

        $app = new Application();
        $originalRouter = new Router($app);
        $app->instance(Router::class, $originalRouter);
        $app->instance('router', $originalRouter);

        $config = new ConfigRepository([
            'cache' => [
                'routes' => [
                    'path' => $this->cachePath,
                ],
            ],
        ]);

        $writtenPath = RouteCache::buildFromSource($app, $config);

        self::assertSame($this->cachePath, $writtenPath);
        self::assertSame($originalRouter, $app->make(Router::class));
        self::assertStringContainsString($routePath, (string) file_get_contents($this->cachePath));
        self::assertStringContainsString("\$route->middleware(...array (\n  0 => 'auth',", (string) file_get_contents($this->cachePath));
    }
}
