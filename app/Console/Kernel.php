<?php

declare(strict_types=1);

namespace App\Console;

use App\Console\Commands\MaintenanceOffCommand;
use App\Console\Commands\MaintenanceOnCommand;
use App\Console\Commands\MaintenanceStatusCommand;
use App\Console\Commands\RouteCacheCommand;
use App\Console\Commands\RouteClearCommand;
use App\Console\Commands\VersionShowCommand;
use App\Console\Commands\VersionSyncCommand;
use App\Config\ConfigRepository;
use App\Maintenance\MaintenanceMode;
use App\Version\ApplicationVersion;
use Myxa\Application;
use Myxa\Console\ConsoleKernel;

final class Kernel extends ConsoleKernel
{
    public function __construct(private readonly Application $app)
    {
        parent::__construct($app, version: $app->make(ApplicationVersion::class)->current());
    }

    protected function commands(): iterable
    {
        return [
            MaintenanceOnCommand::class,
            MaintenanceOffCommand::class,
            MaintenanceStatusCommand::class,
            VersionSyncCommand::class,
            VersionShowCommand::class,
            RouteCacheCommand::class,
            RouteClearCommand::class,
        ];
    }

    /**
     * @param list<string> $argv
     */
    public function handle(array $argv): int
    {
        $context = $this->parseContext($argv);

        if ($this->shouldBypassMaintenanceLock($context['command'], $context['help'], $context['version'])) {
            return parent::handle($argv);
        }

        $maintenance = $this->app->make(MaintenanceMode::class);

        if ($maintenance->isEnabled()) {
            if (!$context['quiet']) {
                file_put_contents('php://stderr', "Application is in maintenance mode.\n");
            }

            return 1;
        }

        $activityToken = $maintenance->beginConsoleActivity($context['command'] ?? 'unknown');

        try {
            return parent::handle($argv);
        } finally {
            $maintenance->endConsoleActivity($activityToken);
        }
    }

    /**
     * @param list<string> $argv
     * @return array{command: string|null, help: bool, quiet: bool, version: bool}
     */
    private function parseContext(array $argv): array
    {
        $tokens = array_values($argv);
        array_shift($tokens);

        $command = null;
        $help = false;
        $quiet = false;
        $version = false;

        foreach ($tokens as $token) {
            if ($command === null && !str_starts_with($token, '--')) {
                $command = $token;

                continue;
            }

            if ($token === '--help') {
                $help = true;
            }

            if ($token === '--quiet') {
                $quiet = true;
            }

            if ($token === '--version') {
                $version = true;
            }
        }

        return [
            'command' => $command,
            'help' => $help,
            'quiet' => $quiet,
            'version' => $version,
        ];
    }

    private function shouldBypassMaintenanceLock(?string $command, bool $help, bool $version): bool
    {
        if ($help || $version) {
            return true;
        }

        if ($command === null || in_array($command, ['list', 'help'], true)) {
            return true;
        }

        if (in_array($command, [
            'maintenance:on',
            'maintenance:off',
            'maintenance:status',
            'version:sync',
            'version:show',
        ], true)) {
            return true;
        }

        return $this->matchesMaintenanceAllowList($command);
    }

    private function matchesMaintenanceAllowList(string $command): bool
    {
        $config = $this->app->make(ConfigRepository::class);
        $patterns = $config->get('maintenance.allowed_commands', []);

        if (!is_array($patterns)) {
            return false;
        }

        foreach ($patterns as $pattern) {
            if (!is_string($pattern) || trim($pattern) === '') {
                continue;
            }

            if ($this->commandMatchesPattern($command, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function commandMatchesPattern(string $command, string $pattern): bool
    {
        $pattern = trim($pattern);

        if ($pattern === $command) {
            return true;
        }

        if (!str_contains($pattern, '*')) {
            return false;
        }

        $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#';

        return preg_match($regex, $command) === 1;
    }
}
