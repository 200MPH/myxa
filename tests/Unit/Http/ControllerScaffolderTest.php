<?php

declare(strict_types=1);

namespace Test\Unit\Http;

use App\Http\ControllerScaffolder;
use PHPUnit\Framework\Attributes\CoversClass;
use Test\TestCase;

#[CoversClass(ControllerScaffolder::class)]
final class ControllerScaffolderTest extends TestCase
{
    private string $controllersPath;

    protected function setUp(): void
    {
        parent::setUp();

        $rootPath = sys_get_temp_dir() . '/myxa-controller-scaffolder-' . uniqid('', true);
        $this->controllersPath = $rootPath . '/app/Http/Controllers';

        mkdir($this->controllersPath, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory(dirname(dirname(dirname($this->controllersPath))));

        parent::tearDown();
    }

    public function testMakeCreatesDefaultControllerWithIndexAction(): void
    {
        $scaffolder = new ControllerScaffolder($this->controllersPath);

        $result = $scaffolder->make('Dashboard');
        $source = file_get_contents($result['path']);

        self::assertSame($this->controllersPath . '/DashboardController.php', $result['path']);
        self::assertSame('App\\Http\\Controllers\\DashboardController', $result['class']);
        self::assertSame('controller', $result['style']);
        self::assertIsString($source);
        self::assertStringContainsString('final class DashboardController', $source);
        self::assertStringContainsString('public function index(Request $request): Response', $source);
    }

    public function testMakeCreatesInvokableController(): void
    {
        $scaffolder = new ControllerScaffolder($this->controllersPath);

        $result = $scaffolder->make('HealthCheck', invokable: true);
        $source = file_get_contents($result['path']);

        self::assertSame('invokable', $result['style']);
        self::assertIsString($source);
        self::assertStringContainsString('public function __invoke(Request $request): Response', $source);
        self::assertStringNotContainsString('public function index(Request $request): Response', $source);
    }

    public function testMakeCreatesResourceControllerInNestedNamespace(): void
    {
        $scaffolder = new ControllerScaffolder($this->controllersPath);

        $result = $scaffolder->make('Admin\\UsersController', resource: true);
        $source = file_get_contents($result['path']);

        self::assertSame($this->controllersPath . '/Admin/UsersController.php', $result['path']);
        self::assertSame('App\\Http\\Controllers\\Admin\\UsersController', $result['class']);
        self::assertSame('resource', $result['style']);
        self::assertIsString($source);
        self::assertStringContainsString('namespace App\\Http\\Controllers\\Admin;', $source);
        self::assertStringContainsString('public function store(Request $request): Response', $source);
        self::assertStringContainsString('public function destroy(string $id): Response', $source);
    }

    public function testMakeAcceptsSlashDelimitedControllerNames(): void
    {
        $scaffolder = new ControllerScaffolder($this->controllersPath);

        $result = $scaffolder->make('Test/TestPala');
        $source = file_get_contents($result['path']);

        self::assertSame($this->controllersPath . '/Test/TestPalaController.php', $result['path']);
        self::assertSame('App\\Http\\Controllers\\Test\\TestPalaController', $result['class']);
        self::assertIsString($source);
        self::assertStringContainsString('namespace App\\Http\\Controllers\\Test;', $source);
        self::assertStringContainsString('final class TestPalaController', $source);
    }

    public function testHelperMethodsNormalizeControllerNamesNamespacesAndPaths(): void
    {
        $scaffolder = new ControllerScaffolder($this->controllersPath);

        self::assertSame('Admin\\Users', $scaffolder->normalizeName('/Admin/Users/'));
        self::assertSame('App\\Http\\Controllers\\Admin', $scaffolder->normalizeNamespace('Admin\\Users'));
        self::assertSame(
            $this->controllersPath . '/Admin/UsersController.php',
            $scaffolder->controllerPath('App\\Http\\Controllers\\Admin', 'UsersController'),
        );
    }

    public function testHelperMethodsRejectInvalidControllerInputs(): void
    {
        $scaffolder = new ControllerScaffolder($this->controllersPath);

        try {
            $scaffolder->make('Dashboard', invokable: true, resource: true);
            self::fail('Expected conflicting controller styles to fail.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('Choose either an invokable controller or a resource controller', $exception->getMessage());
        }

        try {
            $scaffolder->normalizeName('////');
            self::fail('Expected blank controller names to fail.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('could not be resolved', $exception->getMessage());
        }

        try {
            $scaffolder->normalizeNamespace('App\\Models\\User');
            self::fail('Expected invalid controller namespace to fail.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('must live under App\\Http\\Controllers', $exception->getMessage());
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
