<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Http\MiddlewareScaffolder;
use Myxa\Console\Command;
use Myxa\Console\InputArgument;

final class MakeMiddlewareCommand extends Command
{
    /**
     * Scaffold new HTTP middleware under the app middleware namespace.
     */
    public function __construct(private readonly MiddlewareScaffolder $middleware)
    {
    }

    public function name(): string
    {
        return 'make:middleware';
    }

    public function description(): string
    {
        return 'Generate a new HTTP middleware class.';
    }

    public function parameters(): array
    {
        return [
            new InputArgument('name', 'Middleware class name, for example EnsureTenantMiddleware or Admin\\EnsureTenantMiddleware.'),
        ];
    }

    protected function handle(): int
    {
        $result = $this->middleware->make((string) $this->parameter('name'));

        $this->table(
            ['Class', 'Path'],
            [[
                $result['class'],
                $result['path'],
            ]],
        );

        $this->success('Middleware scaffolded successfully.')->icon();

        return 0;
    }
}
