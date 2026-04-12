<?php

declare(strict_types=1);

namespace Test\Unit\Routing;

use App\Routing\RouteCache;
use Myxa\Application;
use Myxa\Routing\Router;
use PHPUnit\Framework\Attributes\CoversClass;
use Test\TestCase;

#[CoversClass(RouteCache::class)]
final class RouteCacheTest extends TestCase
{
    public function testCompileRejectsClosureHandlers(): void
    {
        $router = new Router(new Application());
        $router->get('/closure-route', static fn (): string => 'nope');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to cache route [/closure-route]');

        RouteCache::compile($router->routes());
    }
}
