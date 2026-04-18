<?php

declare(strict_types=1);

namespace App\Routing;

use App\Config\ConfigRepository;
use Myxa\Application;
use Myxa\Routing\RouteDefinition;
use Myxa\Routing\Router;
use Myxa\Support\Facades\Route;
use RuntimeException;

final class RouteCache
{
    public static function isEnabled(ConfigRepository $config): bool
    {
        return $config->get('cache.routes.enabled', false) === true;
    }

    public static function path(ConfigRepository $config): string
    {
        $path = $config->get('cache.routes.path', storage_path('cache/framework/routes.php'));

        if (!is_string($path) || trim($path) === '') {
            return storage_path('cache/framework/routes.php');
        }

        return $path;
    }

    public static function exists(ConfigRepository $config): bool
    {
        return is_file(self::path($config));
    }

    /**
     * @return list<string>
     */
    public static function sourceFiles(): array
    {
        $routeFiles = glob(route_path('*.php')) ?: [];
        sort($routeFiles);

        return array_values(array_filter(
            $routeFiles,
            static fn (mixed $routeFile): bool => is_string($routeFile) && is_file($routeFile),
        ));
    }

    public static function loadSourceRoutes(): void
    {
        foreach (self::sourceFiles() as $routeFile) {
            require $routeFile;
        }
    }

    public static function loadCachedRoutes(ConfigRepository $config): bool
    {
        $path = self::path($config);
        if (!is_file($path)) {
            return false;
        }

        require $path;

        return true;
    }

    public static function buildFromSource(Application $app, ConfigRepository $config): string
    {
        $originalRouter = $app->make(Router::class);
        $freshRouter = new Router($app);

        $app->instance(Router::class, $freshRouter);
        $app->instance('router', $freshRouter);
        Route::setRouter($freshRouter);

        try {
            self::loadSourceRoutes();
            $compiled = self::compile($freshRouter->routes());
        } finally {
            $app->instance(Router::class, $originalRouter);
            $app->instance('router', $originalRouter);
            Route::setRouter($originalRouter);
        }

        $path = self::path($config);
        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create route cache directory [%s].', $directory));
        }

        if (file_put_contents($path, $compiled) === false) {
            throw new RuntimeException(sprintf('Unable to write route cache file [%s].', $path));
        }

        return $path;
    }

    public static function clear(ConfigRepository $config): bool
    {
        $path = self::path($config);

        return !is_file($path) || unlink($path);
    }

    /**
     * @param list<RouteDefinition> $routes
     */
    public static function compile(array $routes): string
    {
        $lines = [
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
            'use Myxa\\Support\\Facades\\Route;',
            '',
        ];

        foreach ($routes as $route) {
            $methods = self::exportValue($route->methods(), $route, 'methods');
            $path = var_export($route->path(), true);
            $handler = self::exportValue($route->handler(), $route, 'handler');

            $lines[] = sprintf('$route = Route::match(%s, %s, %s);', $methods, $path, $handler);

            if ($route->middlewares() !== []) {
                $middlewares = self::exportValue($route->middlewares(), $route, 'middlewares');
                $lines[] = sprintf('$route->middleware(...%s);', $middlewares);
            }

            $lines[] = '';
        }

        return implode(PHP_EOL, $lines);
    }

    private static function exportValue(mixed $value, RouteDefinition $route, string $context): string
    {
        self::assertCacheableValue($value, $route, $context);

        return var_export($value, true);
    }

    private static function assertCacheableValue(mixed $value, RouteDefinition $route, string $context): void
    {
        if ($value instanceof \Closure) {
            throw new RuntimeException(sprintf(
                'Unable to cache route [%s]: %s contains a Closure. '
                . 'Use a controller action or class-based middleware instead.',
                $route->path(),
                $context,
            ));
        }

        if (is_object($value) || is_resource($value)) {
            throw new RuntimeException(sprintf(
                'Unable to cache route [%s]: %s contains an unsupported %s value.',
                $route->path(),
                $context,
                get_debug_type($value),
            ));
        }

        if (!is_array($value)) {
            return;
        }

        foreach ($value as $nestedValue) {
            self::assertCacheableValue($nestedValue, $route, $context);
        }
    }
}
