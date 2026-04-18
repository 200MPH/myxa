<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Events\EventScaffolder;
use Myxa\Console\Command;
use Myxa\Console\InputArgument;

final class MakeEventCommand extends Command
{
    /**
     * Scaffold new application event classes under the app event namespace.
     */
    public function __construct(private readonly EventScaffolder $events)
    {
    }

    public function name(): string
    {
        return 'make:event';
    }

    public function description(): string
    {
        return 'Generate a new application event class.';
    }

    public function parameters(): array
    {
        return [
            new InputArgument('name', 'Event class name, for example UserRegistered or Auth\\UserLoggedIn.'),
        ];
    }

    protected function handle(): int
    {
        $result = $this->events->make((string) $this->parameter('name'));

        $this->table(
            ['Class', 'Path'],
            [[
                $result['class'],
                $result['path'],
            ]],
        );

        $this->success('Event scaffolded successfully.')->icon();

        return 0;
    }
}
