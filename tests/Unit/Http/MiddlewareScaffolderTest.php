<?php

declare(strict_types=1);

namespace Test\Unit\Http;

use App\Http\MiddlewareScaffolder;
use PHPUnit\Framework\Attributes\CoversClass;
use Test\TestCase;

#[CoversClass(MiddlewareScaffolder::class)]
final class MiddlewareScaffolderTest extends TestCase
{
    private string $middlewarePath;

    protected function setUp(): void
    {
        parent::setUp();

        $rootPath = sys_get_temp_dir() . '/myxa-middleware-scaffolder-' . uniqid('', true);
        $this->middlewarePath = $rootPath . '/app/Http/Middleware';

        mkdir($this->middlewarePath, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory(dirname(dirname(dirname($this->middlewarePath))));

        parent::tearDown();
    }

    public function testMakeCreatesDefaultMiddleware(): void
    {
        $scaffolder = new MiddlewareScaffolder($this->middlewarePath);

        $result = $scaffolder->make('EnsureTenant');
        $source = file_get_contents($result['path']);

        self::assertSame($this->middlewarePath . '/EnsureTenantMiddleware.php', $result['path']);
        self::assertSame('App\\Http\\Middleware\\EnsureTenantMiddleware', $result['class']);
        self::assertIsString($source);
        self::assertStringContainsString('final class EnsureTenantMiddleware implements MiddlewareInterface', $source);
        self::assertStringContainsString('public function handle(Request $request, Closure $next, RouteDefinition $route): mixed', $source);
        self::assertStringContainsString('return $next();', $source);
    }

    public function testMakeCreatesNestedMiddleware(): void
    {
        $scaffolder = new MiddlewareScaffolder($this->middlewarePath);

        $result = $scaffolder->make('Admin\\EnsureTenantMiddleware');
        $source = file_get_contents($result['path']);

        self::assertSame($this->middlewarePath . '/Admin/EnsureTenantMiddleware.php', $result['path']);
        self::assertSame('App\\Http\\Middleware\\Admin\\EnsureTenantMiddleware', $result['class']);
        self::assertIsString($source);
        self::assertStringContainsString('namespace App\\Http\\Middleware\\Admin;', $source);
    }

    public function testMakeAcceptsSlashDelimitedMiddlewareNames(): void
    {
        $scaffolder = new MiddlewareScaffolder($this->middlewarePath);

        $result = $scaffolder->make('Api/EnsureTokenScope');
        $source = file_get_contents($result['path']);

        self::assertSame($this->middlewarePath . '/Api/EnsureTokenScopeMiddleware.php', $result['path']);
        self::assertSame('App\\Http\\Middleware\\Api\\EnsureTokenScopeMiddleware', $result['class']);
        self::assertIsString($source);
        self::assertStringContainsString('namespace App\\Http\\Middleware\\Api;', $source);
        self::assertStringContainsString('final class EnsureTokenScopeMiddleware implements MiddlewareInterface', $source);
    }

    public function testHelperMethodsNormalizeMiddlewareNamesNamespacesAndPaths(): void
    {
        $scaffolder = new MiddlewareScaffolder($this->middlewarePath);

        self::assertSame('Api\\EnsureTokenScope', $scaffolder->normalizeName('/Api/EnsureTokenScope/'));
        self::assertSame('App\\Http\\Middleware\\Api', $scaffolder->normalizeNamespace('Api\\EnsureTokenScope'));
        self::assertSame(
            $this->middlewarePath . '/Api/EnsureTokenScopeMiddleware.php',
            $scaffolder->middlewareClassPath('App\\Http\\Middleware\\Api', 'EnsureTokenScopeMiddleware'),
        );
    }

    public function testHelperMethodsRejectInvalidMiddlewareInputs(): void
    {
        $scaffolder = new MiddlewareScaffolder($this->middlewarePath);

        try {
            $scaffolder->normalizeName('////');
            self::fail('Expected blank middleware names to fail.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('could not be resolved', $exception->getMessage());
        }

        try {
            $scaffolder->normalizeNamespace('App\\Models\\User');
            self::fail('Expected invalid middleware namespace to fail.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('must live under App\\Http\\Middleware', $exception->getMessage());
        }

        try {
            $scaffolder->middlewareClassPath('App\\Models', 'UserMiddleware');
            self::fail('Expected invalid middleware path namespace to fail.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('must live under App\\Http\\Middleware', $exception->getMessage());
        }
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
