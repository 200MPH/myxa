<?php

declare(strict_types=1);

namespace Test\Unit\Console;

use App\Console\Commands\MaintenanceOffCommand;
use App\Console\Commands\MaintenanceOnCommand;
use App\Console\Commands\MaintenanceStatusCommand;
use App\Console\Kernel;
use App\Console\Commands\RouteCacheCommand;
use App\Console\Commands\RouteClearCommand;
use App\Console\Commands\VersionShowCommand;
use App\Console\Commands\VersionSyncCommand;
use App\Config\ConfigRepository;
use App\Foundation\ApplicationFactory;
use App\Maintenance\MaintenanceMode;
use App\Providers\QueueServiceProvider;
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
#[CoversClass(VersionSyncCommand::class)]
#[CoversClass(VersionShowCommand::class)]
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

    public function testMaintenanceOnCommandSupportsWaitWhenNoActiveCommandsRemain(): void
    {
        $app = ApplicationFactory::create(base_path());
        $command = $app->make(MaintenanceOnCommand::class);

        $stream = fopen('php://temp', 'w+b');
        self::assertIsResource($stream);

        $exitCode = $command->run(
            new ConsoleInput('maintenance:on', [], ['wait' => true]),
            new ConsoleOutput($stream, ansi: false),
        );

        rewind($stream);
        $output = (string) stream_get_contents($stream);
        fclose($stream);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Maintenance mode enabled', $output);
        self::assertStringContainsString('No running console commands are still active.', $output);
    }

    public function testMaintenanceOnCommandReportsAlreadyEnabledAndCanTimeOutWaiting(): void
    {
        $app = ApplicationFactory::create(base_path());
        $maintenance = $app->make(MaintenanceMode::class);
        $maintenance->enable('phpunit');
        $maintenance->beginConsoleActivity('queue:work');

        $command = $app->make(MaintenanceOnCommand::class);
        $stream = fopen('php://temp', 'w+b');
        self::assertIsResource($stream);

        $exitCode = $command->run(
            new ConsoleInput('maintenance:on', [], ['wait' => true, 'timeout' => 1]),
            new ConsoleOutput($stream, ansi: false),
        );

        rewind($stream);
        $output = (string) stream_get_contents($stream);
        fclose($stream);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Maintenance mode is already enabled.', $output);
        self::assertStringContainsString('Timed out while waiting for maintenance drain.', $output);
    }

    public function testMaintenanceOffCommandReportsAlreadyDisabled(): void
    {
        $app = ApplicationFactory::create(base_path());

        [$exitCode, $output] = $this->runCommand($app->make(MaintenanceOffCommand::class), 'maintenance:off');

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Maintenance mode is already disabled.', $output);
    }

    public function testRouteAndMaintenanceCommandsExposeCliMetadata(): void
    {
        $app = ApplicationFactory::create(base_path());

        $routeCache = $app->make(RouteCacheCommand::class);
        $routeClear = $app->make(RouteClearCommand::class);
        $maintenanceOn = $app->make(MaintenanceOnCommand::class);
        $maintenanceOff = $app->make(MaintenanceOffCommand::class);

        self::assertSame('route:cache', $routeCache->name());
        self::assertSame('Compile application routes into a cached PHP manifest.', $routeCache->description());
        self::assertSame('route:clear', $routeClear->name());
        self::assertSame('Delete the compiled route cache manifest.', $routeClear->description());
        self::assertSame('maintenance:on', $maintenanceOn->name());
        self::assertSame(
            'Enable maintenance mode and optionally wait for running CLI work to finish.',
            $maintenanceOn->description(),
        );
        self::assertCount(2, $maintenanceOn->options());
        self::assertSame('wait', $maintenanceOn->options()[0]->name());
        self::assertFalse($maintenanceOn->options()[0]->acceptsValue());
        self::assertSame('timeout', $maintenanceOn->options()[1]->name());
        self::assertTrue($maintenanceOn->options()[1]->acceptsValue());
        self::assertSame(300, $maintenanceOn->options()[1]->default());
        self::assertSame('maintenance:off', $maintenanceOff->name());
        self::assertSame('Disable maintenance mode.', $maintenanceOff->description());
    }

    public function testMaintenanceStatusCommandDisplaysTrackedCommandsTable(): void
    {
        $app = ApplicationFactory::create(base_path());
        $maintenance = $app->make(MaintenanceMode::class);
        $maintenance->enable('phpunit');
        $maintenance->beginConsoleActivity('queue:work');

        [$exitCode, $output] = $this->runCommand(
            $app->make(MaintenanceStatusCommand::class),
            'maintenance:status',
        );

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('enabled', $output);
        self::assertStringContainsString('queue:work', $output);
        self::assertStringContainsString('Started At', $output);
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

    public function testConsoleKernelReturnsErrorWhenMaintenanceModeIsEnabled(): void
    {
        $app = ApplicationFactory::create(base_path());
        $maintenance = $app->make(MaintenanceMode::class);
        $maintenance->enable('phpunit');

        try {
            $exitCode = $app->make(Kernel::class)->handle(['myxa', 'route:clear', '--quiet']);

            self::assertSame(1, $exitCode);
        } finally {
            $maintenance->disable();
        }
    }

    public function testConsoleKernelTracksAndCompletesNonBypassedCommands(): void
    {
        $app = ApplicationFactory::create(base_path());
        $maintenance = $app->make(MaintenanceMode::class);

        $exitCode = $app->make(Kernel::class)->handle(['myxa', 'cache:clear', '--quiet']);

        self::assertSame(0, $exitCode);
        self::assertSame(0, $maintenance->activeConsoleCommandCount());
    }

    public function testConsoleKernelHelperMethodsParseContextAndMatchWildcardAllowList(): void
    {
        $app = ApplicationFactory::create(base_path());
        $kernel = $app->make(Kernel::class);

        $parseContext = new \ReflectionMethod(Kernel::class, 'parseContext');
        $parseContext->setAccessible(true);
        $matchesPattern = new \ReflectionMethod(Kernel::class, 'commandMatchesPattern');
        $matchesPattern->setAccessible(true);
        $allowList = new \ReflectionMethod(Kernel::class, 'matchesMaintenanceAllowList');
        $allowList->setAccessible(true);

        $context = $parseContext->invoke($kernel, ['myxa', 'cache:clear', '--quiet', '--help']);
        $app->make(ConfigRepository::class)->set('maintenance.allowed_commands', ['cache:*']);

        self::assertSame('cache:clear', $context['command']);
        self::assertTrue($context['quiet']);
        self::assertTrue($context['help']);
        self::assertFalse($context['version']);
        self::assertTrue($matchesPattern->invoke($kernel, 'cache:forget', 'cache:*'));
        self::assertFalse($matchesPattern->invoke($kernel, 'route:clear', 'cache:*'));
        self::assertTrue($allowList->invoke($kernel, 'cache:clear'));
    }

    public function testConsoleKernelBypassAndAllowListHelpersHandleSpecialCases(): void
    {
        $app = ApplicationFactory::create(base_path());
        $kernel = $app->make(Kernel::class);

        $bypass = new \ReflectionMethod(Kernel::class, 'shouldBypassMaintenanceLock');
        $bypass->setAccessible(true);
        $matchesPattern = new \ReflectionMethod(Kernel::class, 'commandMatchesPattern');
        $matchesPattern->setAccessible(true);
        $allowList = new \ReflectionMethod(Kernel::class, 'matchesMaintenanceAllowList');
        $allowList->setAccessible(true);

        self::assertTrue($bypass->invoke($kernel, null, false, false));
        self::assertTrue($bypass->invoke($kernel, 'list', false, false));
        self::assertTrue($bypass->invoke($kernel, 'help', false, false));
        self::assertTrue($bypass->invoke($kernel, 'version:sync', false, false));
        self::assertFalse($matchesPattern->invoke($kernel, 'cache:clear', 'cache:clearer'));

        $app->make(ConfigRepository::class)->set('maintenance.allowed_commands', 'invalid');
        self::assertFalse($allowList->invoke($kernel, 'cache:clear'));
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

    public function testConsoleKernelBypassesMaintenanceForHelpAndVersionFlags(): void
    {
        $app = ApplicationFactory::create(base_path());
        $maintenance = $app->make(MaintenanceMode::class);
        $maintenance->enable('phpunit');

        $kernel = $app->make(Kernel::class);

        self::assertSame(0, $kernel->handle(['myxa', 'route:cache', '--help', '--quiet']));
        self::assertSame(0, $kernel->handle(['myxa', '--version', '--quiet']));

        $maintenance->disable();
    }

    public function testConsoleKernelSkipsQueueCommandsWhenQueueProviderIsDisabled(): void
    {
        $app = ApplicationFactory::create(base_path());
        $config = $app->make(ConfigRepository::class);
        $providers = $config->get('app.providers', []);
        self::assertIsArray($providers);

        $config->set('app.providers', array_values(array_filter(
            $providers,
            static fn (mixed $provider): bool => $provider !== QueueServiceProvider::class,
        )));

        $kernel = new Kernel($app);
        $commandsMethod = new \ReflectionMethod(Kernel::class, 'commands');
        $commandsMethod->setAccessible(true);
        $commands = $commandsMethod->invoke($kernel);

        self::assertIsArray($commands);
        self::assertContains(VersionShowCommand::class, $commands);
        self::assertNotContains('App\\Console\\Commands\\QueueStatusCommand', $commands);
        self::assertSame(1, $kernel->handle(['myxa', 'queue:status', '--quiet']));
    }

    public function testRouteCacheCommandWarnsWhenCachingIsDisabled(): void
    {
        $this->setEnvironmentValue('ROUTE_CACHE', 'false');

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
            self::assertStringContainsString('Route caching is currently disabled by configuration.', $output);
            self::assertStringContainsString('Route cache generated at', $output);
        } finally {
            if (is_file($cachePath)) {
                unlink($cachePath);
            }

            if (is_dir($cacheDirectory) && (glob($cacheDirectory . '/*') ?: []) === []) {
                rmdir($cacheDirectory);
            }
        }
    }

    public function testRouteClearCommandReportsAlreadyClearWhenNoManifestExists(): void
    {
        $app = ApplicationFactory::create(base_path());

        [$exitCode, $output] = $this->runCommand($app->make(RouteClearCommand::class), 'route:clear');

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Route cache is already clear.', $output);
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
