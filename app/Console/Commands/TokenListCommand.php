<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Auth\TokenManager;
use App\Auth\UserManager;
use App\Models\User;
use Myxa\Console\Command;
use Myxa\Console\InputArgument;

final class TokenListCommand extends Command
{
    /**
     * List bearer tokens globally or for a specific user.
     */
    public function __construct(
        private readonly TokenManager $tokens,
        private readonly UserManager $users,
    ) {
    }

    public function name(): string
    {
        return 'token:list';
    }

    public function description(): string
    {
        return 'List personal access tokens.';
    }

    public function parameters(): array
    {
        return [
            new InputArgument('user', 'Optional user ID or email address.', false),
        ];
    }

    protected function handle(): int
    {
        $user = $this->resolveOptionalUser();
        $tokens = $this->tokens->list($user);

        if ($tokens === []) {
            $this->info('No tokens found.')->icon();

            return 0;
        }

        $this->table(
            ['ID', 'User ID', 'Name', 'Scopes', 'Expires', 'Revoked', 'Last Used'],
            array_map(
                static fn (\App\Models\PersonalAccessToken $token): array => [
                    (string) $token->getKey(),
                    (string) $token->getAttribute('user_id'),
                    (string) $token->getAttribute('name'),
                    implode(', ', $token->scopeList()),
                    (string) ($token->getAttribute('expires_at') ?? '-'),
                    (string) ($token->getAttribute('revoked_at') ?? '-'),
                    (string) ($token->getAttribute('last_used_at') ?? '-'),
                ],
                $tokens,
            ),
        );

        return 0;
    }

    private function resolveOptionalUser(): ?User
    {
        $identifier = $this->parameter('user');

        if (!is_string($identifier) || trim($identifier) === '') {
            return null;
        }

        return $this->users->resolveUser(trim($identifier));
    }
}
