<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Exceptions\CommandFailedException;
use App\Maintenance\MaintenanceMode;
use Myxa\Console\Command;

final class MaintenanceOffCommand extends Command
{
    public function __construct(private readonly MaintenanceMode $maintenance)
    {
    }

    public function name(): string
    {
        return 'maintenance:off';
    }

    public function description(): string
    {
        return 'Disable maintenance mode.';
    }

    protected function handle(): int
    {
        if (!$this->maintenance->isEnabled()) {
            $this->info('Maintenance mode is already disabled.')->icon();

            return 0;
        }

        if (!$this->maintenance->disable()) {
            throw new CommandFailedException('Unable to remove the maintenance marker file.');
        }

        $this->success('Maintenance mode disabled.')->icon();

        return 0;
    }
}
