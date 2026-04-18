<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Auth\UserManager;
use Myxa\Console\Command;
use Myxa\Console\InputArgument;
use Myxa\Console\InputOption;

final class UserPasswordCommand extends Command
{
    /**
     * Reset or rotate a user's password from the CLI.
     */
    public function __construct(private readonly UserManager $users)
    {
    }

    public function name(): string
    {
        return 'user:password';
    }

    public function description(): string
    {
        return 'Change the password for an existing user.';
    }

    public function parameters(): array
    {
        return [
            new InputArgument('user', 'User ID or email address.'),
        ];
    }

    public function options(): array
    {
        return [
            new InputOption('password', 'Optional new password. One will be generated when omitted.', true),
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

        $user = $this->users->changePassword((string) $this->parameter('user'), $password);

        $this->success(sprintf('Password updated for user [%s].', (string) $user->getAttribute('email')))->icon();

        if ($generated) {
            $this->warning(sprintf('Generated password: %s', $password))->icon();
        }

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
