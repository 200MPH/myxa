<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Auth\UserManager;
use Myxa\Console\Command;
use Myxa\Console\InputArgument;
use Myxa\Console\InputOption;

final class UserCreateCommand extends Command
{
    /**
     * Create users directly from the CLI for bootstrapping and operations.
     */
    public function __construct(private readonly UserManager $users)
    {
    }

    public function name(): string
    {
        return 'user:create';
    }

    public function description(): string
    {
        return 'Create a new application user.';
    }

    public function parameters(): array
    {
        return [
            new InputArgument('email', 'User email address.'),
        ];
    }

    public function options(): array
    {
        return [
            new InputOption('name', 'Optional display name.', true),
            new InputOption('password', 'Optional plain-text password. One will be generated when omitted.', true),
        ];
    }

    protected function handle(): int
    {
        $generated = false;
        $password = $this->stringOption('password');

        if ($password === null) {
            $password = $this->randomPassword();
            $generated = true;
        }

        $user = $this->users->create(
            (string) $this->parameter('email'),
            $password,
            $this->stringOption('name'),
        );

        $this->table(
            ['ID', 'Email', 'Name'],
            [[
                (string) $user->getKey(),
                (string) $user->getAttribute('email'),
                (string) ($user->getAttribute('name') ?? '-'),
            ]],
        );

        if ($generated) {
            $this->warning(sprintf('Generated password: %s', $password))->icon();
        }

        $this->success('User created successfully.')->icon();

        return 0;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function randomPassword(): string
    {
        return substr(bin2hex(random_bytes(12)), 0, 20);
    }
}
