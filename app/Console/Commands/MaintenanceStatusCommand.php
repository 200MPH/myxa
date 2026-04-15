<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Maintenance\MaintenanceMode;
use Myxa\Console\Command;

final class MaintenanceStatusCommand extends Command
{
    public function __construct(private readonly MaintenanceMode $maintenance)
    {
    }

    public function name(): string
    {
        return 'maintenance:status';
    }

    public function description(): string
    {
        return 'Show the current maintenance mode status and tracked CLI activity.';
    }

    protected function handle(): int
    {
        $payload = $this->maintenance->payload();
        $enabled = $this->maintenance->isEnabled();
        $activeCommands = $this->maintenance->activeConsoleCommands();

        $this->table(
            ['State', 'Marker', 'Enabled At', 'Active CLI'],
            [[
                $enabled ? 'enabled' : 'disabled',
                $this->maintenance->markerPath(),
                $payload['enabled_at'] ?? '-',
                (string) count($activeCommands),
            ]],
        );

        if ($activeCommands === []) {
            return 0;
        }

        $rows = [];

        foreach ($activeCommands as $command) {
            $rows[] = [
                $command['command'] ?? '-',
                (string) ($command['pid'] ?? '-'),
                $command['started_at'] ?? '-',
            ];
        }

        $this->output('');
        $this->table(['Command', 'PID', 'Started At'], $rows);

        return 0;
    }
}
