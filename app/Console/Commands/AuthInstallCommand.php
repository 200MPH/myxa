<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Auth\AuthInstallService;
use Myxa\Console\Command;
use Myxa\Console\InputOption;

final class AuthInstallCommand extends Command
{
    /**
     * Generate the auth storage migrations used by the app auth layer.
     */
    public function __construct(private readonly AuthInstallService $auth)
    {
    }

    public function name(): string
    {
        return 'auth:install';
    }

    public function description(): string
    {
        return 'Create missing auth migration files for users, sessions, and bearer tokens.';
    }

    public function options(): array
    {
        return [
            new InputOption('without-sessions', 'Skip generating the database-backed session migration.'),
        ];
    }

    protected function handle(): int
    {
        $result = $this->auth->install(!$this->booleanOption('without-sessions'));

        if ($result['created'] !== []) {
            $this->table(
                ['Created Migrations'],
                array_map(static fn (string $path): array => [$path], $result['created']),
            );
        }

        if ($result['skipped'] !== []) {
            $this->table(
                ['Skipped Existing'],
                array_map(static fn (string $name): array => [$name], $result['skipped']),
            );
        }

        $this->success('Auth install complete. Run `./myxa migrate` next.')->icon();

        return 0;
    }

    private function booleanOption(string $name): bool
    {
        return $this->option($name, false) === true;
    }
}
