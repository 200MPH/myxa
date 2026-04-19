<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Exceptions\CommandFailedException;
use App\Maintenance\MaintenanceMode;
use Myxa\Console\Command;
use Myxa\Console\InputOption;

final class MaintenanceOnCommand extends Command
{
    public function __construct(private readonly MaintenanceMode $maintenance)
    {
    }

    public function name(): string
    {
        return 'maintenance:on';
    }

    public function description(): string
    {
        return 'Enable maintenance mode and optionally wait for running CLI work to finish.';
    }

    public function options(): array
    {
        return [
            new InputOption(
                'wait',
                'Wait for already-running console commands to finish before returning.',
            ),
            new InputOption(
                'timeout',
                'Maximum seconds to wait when --wait is used. Use 0 to wait indefinitely.',
                true,
                false,
                300,
            ),
        ];
    }

    protected function handle(): int
    {
        $wasEnabled = $this->maintenance->isEnabled();

        if (!$wasEnabled) {
            $this->maintenance->enable($this->input()->command());
            $this->success(sprintf(
                'Maintenance mode enabled with marker %s',
                $this->maintenance->markerPath(),
            ))->icon();
        } else {
            $this->info('Maintenance mode is already enabled.')->icon();
        }

        if (!$this->option('wait', false)) {
            return 0;
        }

        $timeout = $this->normalizeTimeout($this->option('timeout', 300));
        $activeBeforeWait = $this->maintenance->activeConsoleCommandCount();

        if ($activeBeforeWait === 0) {
            $this->info('No running console commands are still active.')->icon();

            return 0;
        }

        $this->info(sprintf(
            'Waiting for %d running console command(s) to finish...',
            $activeBeforeWait,
        ))->icon();

        if (!$this->maintenance->waitForIdleConsole($timeout)) {
            $remaining = $this->maintenance->activeConsoleCommandCount();

            throw new CommandFailedException(sprintf(
                'Timed out while waiting for maintenance drain. %d command(s) still active.',
                $remaining,
            ));
        }

        $this->success('Maintenance drain complete.')->icon();

        return 0;
    }

    private function normalizeTimeout(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return 300;
        }

        if (is_numeric($value)) {
            $timeout = (int) $value;

            return $timeout > 0 ? $timeout : null;
        }

        return 300;
    }
}
