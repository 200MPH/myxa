<?php

declare(strict_types=1);

namespace App\Console;

use App\Console\Commands\RouteCacheCommand;
use App\Console\Commands\RouteClearCommand;
use Myxa\Console\ConsoleKernel;

final class Kernel extends ConsoleKernel
{
    protected function commands(): iterable
    {
        return [
            RouteCacheCommand::class,
            RouteClearCommand::class,
        ];
    }
}
