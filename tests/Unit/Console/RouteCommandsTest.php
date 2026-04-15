<?php

declare(strict_types=1);

namespace Test\Unit\Console;

use App\Console\Commands\MaintenanceOffCommand;
use App\Console\Commands\MaintenanceOnCommand;
use App\Console\Commands\MaintenanceStatusCommand;
use App\Console\Kernel;
use App\Console\Commands\RouteCacheCommand;
use App\Console\Commands\RouteClearCommand;
use App\Config\ConfigRepository;
use App\Foundation\ApplicationFactory;
use App\Maintenance\MaintenanceMode;
use Myxa\Console\CommandInterface;
use Myxa\Console\ConsoleInput;
use Myxa\Console\ConsoleOutput;
use PHPUnit\Framework\Attributes\CoversClass;
use Test\TestCase;

#[CoversClass(RouteCacheCommand::class)]
#[CoversClass(RouteClearCommand::class)]
#[CoversClass(MaintenanceOnCommand::class)]
#[CoversClass(MaintenanceOffCommand::class)]
#[CoversClass(MaintenanceStatusCommand::class)]
#[CoversClass(Kernel::class)]
final class RouteCommandsTest extends TestCase
{
    private string $maintenancePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->maintenancePath = base_path('maintenance.json');

        if (is_file($this->maintenancePath)) {
            unlink($this->maintenancePath);
        }
    }

    protected function tearDown(): void
    {
        if (is_file($this->maintenancePath)) {
            unlink($this->maintenancePath);
        }

        $activityPath = storage_path('maintenance/console-activity.json');
        if (is_file($activityPath)) {
            unlink($activityPath);
        }

        $directory = dirname($activityPath);
        if (is_dir($directory) && (glob($directory . '/*') ?: []) === []) {
            rmdir($directory);
        }

        parent::tearDown();
    }

    public function testRouteCacheCommandBuildsCompiledManifest(): void
    {
        $this->setEnvironmentValue('APP_ENV', 'production');
        $this->setEnvironmentValue('ROUTE_CACHE', 'true');

        $cachePath = storage_path('cache/framework/routes.php');
        $cacheDirectory = dirname($cachePath);

        if (!is_dir($cacheDirectory)) {
            mkdir($cacheDirectory, 0777, true);
        }

        $app = ApplicationFactory::create(base_path());
        $command = $app->make(RouteCacheCommand::class);

        [$exitCode, $output] = $this->runCommand($command, 'route:cache');

        try {
            self::assertSame(0, $exitCode);
            self::assertStringContainsString('Route cache generated at', $output);
            self::assertFileExists($cachePath);

            $compiled = file_get_contents($cachePath);

            self::assertIsString($compiled);
            self::assertStringContainsString("Route::match(array (\n  0 => 'GET',\n), '/'", $compiled);
            self::assertStringContainsString("App\\\\Http\\\\Controllers\\\\HealthController", $compiled);
        } finally {
            if (is_file($cachePath)) {
                unlink($cachePath);
            }

            if (is_dir($cacheDirectory) && (glob($cacheDirectory . '/*') ?: []) === []) {
                rmdir($cacheDirectory);
            }
        }
    }

    public function testRouteClearCommandDeletesCompiledManifest(): void
    {
        $cachePath = storage_path('cache/framework/routes.php');
        $cacheDirectory = dirname($cachePath);

        if (!is_dir($cacheDirectory)) {
            mkdir($cacheDirectory, 0777, true);
        }

        file_put_contents($cachePath, '<?php return [];');

        $app = ApplicationFactory::create(base_path());
        $command = $app->make(RouteClearCommand::class);

        [$exitCode, $output] = $this->runCommand($command, 'route:clear');

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Route cache cleared.', $output);
        self::assertFileDoesNotExist($cachePath);

        if (is_dir($cacheDirectory) && (glob($cacheDirectory . '/*') ?: []) === []) {
            rmdir($cacheDirectory);
        }
    }

    public function testMaintenanceCommandsToggleMarkerFile(): void
    {
        $app = ApplicationFactory::create(base_path());

        [$onExitCode, $onOutput] = $this->runCommand($app->make(MaintenanceOnCommand::class), 'maintenance:on');

        self::assertSame(0, $onExitCode);
        self::assertStringContainsString('Maintenance mode enabled', $onOutput);
        self::assertFileExists($this->maintenancePath);

        [$statusExitCode, $statusOutput] = $this->runCommand(
            $app->make(MaintenanceStatusCommand::class),
            'maintenance:status',
        );

        self::assertSame(0, $statusExitCode);
        self::assertStringContainsString('enabled', $statusOutput);
        self::assertStringContainsString('maintenance.json', $statusOutput);

        [$offExitCode, $offOutput] = $this->runCommand($app->make(MaintenanceOffCommand::class), 'maintenance:off');

        self::assertSame(0, $offExitCode);
        self::assertStringContainsString('Maintenance mode disabled.', $offOutput);
        self::assertFileDoesNotExist($this->maintenancePath);
    }

    public function testConsoleKernelBlocksNewCommandsWhileMaintenanceModeIsEnabled(): void
    {
        $app = ApplicationFactory::create(base_path());
        $maintenance = $app->make(MaintenanceMode::class);
        $maintenance->enable('phpunit');

        $kernel = $app->make(Kernel::class);

        $exitCode = $kernel->handle(['myxa', 'route:clear', '--quiet']);

        self::assertSame(1, $exitCode);
        self::assertSame(0, $maintenance->activeConsoleCommandCount());

        $maintenance->disable();
    }

    public function testConsoleKernelAllowsConfiguredMaintenanceExceptionCommands(): void
    {
        $app = ApplicationFactory::create(base_path());
        $app->make(ConfigRepository::class)->set('maintenance.allowed_commands', ['route:clear']);

        $maintenance = $app->make(MaintenanceMode::class);
        $maintenance->enable('phpunit');

        $kernel = $app->make(Kernel::class);
        $exitCode = $kernel->handle(['myxa', 'route:clear', '--quiet']);

        self::assertSame(0, $exitCode);
        self::assertSame(0, $maintenance->activeConsoleCommandCount());

        $maintenance->disable();
    }

    public function testConsoleKernelSupportsWildcardMaintenanceExceptions(): void
    {
        $app = ApplicationFactory::create(base_path());
        $app->make(ConfigRepository::class)->set('maintenance.allowed_commands', ['route:*']);

        $maintenance = $app->make(MaintenanceMode::class);
        $maintenance->enable('phpunit');

        $kernel = $app->make(Kernel::class);
        $exitCode = $kernel->handle(['myxa', 'route:cache', '--quiet']);

        self::assertSame(0, $exitCode);
        self::assertSame(0, $maintenance->activeConsoleCommandCount());

        $maintenance->disable();

        $cachePath = storage_path('cache/framework/routes.php');
        if (is_file($cachePath)) {
            unlink($cachePath);
        }
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function runCommand(CommandInterface $command, string $name): array
    {
        $stream = fopen('php://temp', 'w+b');
        self::assertIsResource($stream);

        $exitCode = $command->run(
            new ConsoleInput($name, [], []),
            new ConsoleOutput($stream, ansi: false),
        );

        rewind($stream);
        $output = stream_get_contents($stream);
        fclose($stream);

        return [$exitCode, is_string($output) ? $output : ''];
    }
}
