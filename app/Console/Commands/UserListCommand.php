<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Auth\UserManager;
use Myxa\Console\Command;
use Myxa\Console\InputOption;

final class UserListCommand extends Command
{
    /**
     * List application users for quick operator visibility.
     */
    public function __construct(private readonly UserManager $users)
    {
    }

    public function name(): string
    {
        return 'user:list';
    }

    public function description(): string
    {
        return 'List application users.';
    }

    public function options(): array
    {
        return [
            new InputOption('limit', 'Maximum number of users to display.', true, false, 50),
        ];
    }

    protected function handle(): int
    {
        $rows = array_map(
            static fn (\App\Models\User $user): array => [
                (string) $user->getKey(),
                (string) $user->getAttribute('email'),
                (string) ($user->getAttribute('name') ?? '-'),
                (string) ($user->getAttribute('created_at') ?? '-'),
            ],
            $this->users->list($this->intOption('limit', 50)),
        );

        if ($rows === []) {
            $this->info('No users found.')->icon();

            return 0;
        }

        $this->table(['ID', 'Email', 'Name', 'Created'], $rows);

        return 0;
    }

    private function intOption(string $name, int $default): int
    {
        $value = $this->option($name, $default);

        return is_numeric($value) ? max(1, (int) $value) : $default;
    }
}
