<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Data\DataScaffolder;
use Myxa\Console\Command;
use Myxa\Console\InputArgument;

final class MakeResourceCommand extends Command
{
    /**
     * Scaffold new DTO-style resource classes under the app data namespace.
     */
    public function __construct(private readonly DataScaffolder $data)
    {
    }

    public function name(): string
    {
        return 'make:resource';
    }

    public function description(): string
    {
        return 'Generate a new DTO-style resource data class.';
    }

    public function parameters(): array
    {
        return [
            new InputArgument('name', 'Resource class name, for example UserData or Auth\\LoginData.'),
        ];
    }

    protected function handle(): int
    {
        $result = $this->data->make((string) $this->parameter('name'));

        $this->table(
            ['Class', 'Path'],
            [[
                $result['class'],
                $result['path'],
            ]],
        );

        $this->success('Resource data class scaffolded successfully.')->icon();

        return 0;
    }
}
