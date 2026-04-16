<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Http\ControllerScaffolder;
use Myxa\Console\Command;
use Myxa\Console\InputArgument;
use Myxa\Console\InputOption;

final class MakeControllerCommand extends Command
{
    /**
     * Scaffold new HTTP controllers under the app controller namespace.
     */
    public function __construct(private readonly ControllerScaffolder $controllers)
    {
    }

    public function name(): string
    {
        return 'make:controller';
    }

    public function description(): string
    {
        return 'Generate a new HTTP controller class.';
    }

    public function parameters(): array
    {
        return [
            new InputArgument('name', 'Controller class name, for example UserController or Admin\\UserController.'),
        ];
    }

    public function options(): array
    {
        return [
            new InputOption('invokable', 'Generate a single-action controller with __invoke().'),
            new InputOption('resource', 'Generate a resource-style controller with CRUD action methods.'),
        ];
    }

    protected function handle(): int
    {
        $result = $this->controllers->make(
            (string) $this->parameter('name'),
            $this->booleanOption('invokable'),
            $this->booleanOption('resource'),
        );

        $this->table(
            ['Class', 'Path', 'Style'],
            [[
                $result['class'],
                $result['path'],
                $result['style'],
            ]],
        );

        $this->success('Controller scaffolded successfully.')->icon();

        return 0;
    }

    private function booleanOption(string $name): bool
    {
        return $this->option($name, false) === true;
    }
}
