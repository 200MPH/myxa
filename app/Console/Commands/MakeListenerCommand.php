<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Listeners\ListenerScaffolder;
use Myxa\Console\Command;
use Myxa\Console\InputArgument;
use Myxa\Console\InputOption;

final class MakeListenerCommand extends Command
{
    /**
     * Scaffold new event listeners under the app listener namespace.
     */
    public function __construct(private readonly ListenerScaffolder $listeners)
    {
    }

    public function name(): string
    {
        return 'make:listener';
    }

    public function description(): string
    {
        return 'Generate a new event listener class.';
    }

    public function parameters(): array
    {
        return [
            new InputArgument('name', 'Listener class name, for example SendWelcomeEmail or Auth\\TrackLogin.'),
        ];
    }

    public function options(): array
    {
        return [
            new InputOption(
                'event',
                'Optional event class this listener should target, for example UserRegistered.',
                true,
            ),
        ];
    }

    protected function handle(): int
    {
        $result = $this->listeners->make(
            (string) $this->parameter('name'),
            $this->stringOption('event'),
        );

        $this->table(
            ['Class', 'Path', 'Event'],
            [[
                $result['class'],
                $result['path'],
                $result['event'] ?? '-',
            ]],
        );

        $this->success('Listener scaffolded successfully.')->icon();

        return 0;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
