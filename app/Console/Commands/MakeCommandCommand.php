<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\CommandScaffolder;
use Myxa\Console\Command;
use Myxa\Console\InputArgument;
use Myxa\Console\InputOption;

final class MakeCommandCommand extends Command
{
    /**
     * Scaffold a new console command class and register it in the kernel.
     */
    public function __construct(private readonly CommandScaffolder $scaffolder)
    {
    }

    public function name(): string
    {
        return 'make:command';
    }

    public function description(): string
    {
        return 'Generate a new console command class and register it.';
    }

    public function parameters(): array
    {
        return [
            new InputArgument('name', 'Command class name, for example SendDigestCommand.'),
        ];
    }

    public function options(): array
    {
        return [
            new InputOption('command', 'Explicit console command name, for example reports:send.', true),
            new InputOption('description', 'Optional command description.', true),
        ];
    }

    protected function handle(): int
    {
        $result = $this->scaffolder->make(
            (string) $this->parameter('name'),
            $this->stringOption('command'),
            $this->stringOption('description'),
        );

        $this->table(
            ['Command', 'Class', 'Path'],
            [[
                $result['command'],
                $result['class'],
                $result['path'],
            ]],
        );

        $this->success('Console command scaffolded successfully.')->icon();

        return 0;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
