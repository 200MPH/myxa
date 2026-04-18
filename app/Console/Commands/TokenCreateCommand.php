<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Auth\TokenManager;
use DateTimeImmutable;
use Myxa\Console\Command;
use Myxa\Console\InputArgument;
use Myxa\Console\InputOption;
use RuntimeException;
use Throwable;

final class TokenCreateCommand extends Command
{
    /**
     * Issue bearer tokens for users from the CLI.
     */
    public function __construct(private readonly TokenManager $tokens)
    {
    }

    public function name(): string
    {
        return 'token:create';
    }

    public function description(): string
    {
        return 'Create a personal access token for a user.';
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
            new InputOption('name', 'Optional token display name.', true),
            new InputOption('scopes', 'Comma-separated scopes, for example users:read,users:write.', true),
            new InputOption('expires', 'Optional expiration datetime parseable by PHP.', true),
        ];
    }

    protected function handle(): int
    {
        $result = $this->tokens->issue(
            (string) $this->parameter('user'),
            $this->stringOption('name'),
            $this->parseScopes($this->stringOption('scopes')),
            $this->parseExpiresAt($this->stringOption('expires')),
        );

        $token = $result['token'];

        $this->table(
            ['Token ID', 'User ID', 'Name', 'Scopes', 'Expires At'],
            [[
                (string) $token->getKey(),
                (string) $token->getAttribute('user_id'),
                (string) $token->getAttribute('name'),
                implode(', ', $token->scopeList()),
                (string) ($token->getAttribute('expires_at') ?? '-'),
            ]],
        );
        $this->warning(sprintf('Plain token: %s', $result['plain_text_token']))->icon();
        $this->success('Store the plain token now; it will not be shown again.')->icon();

        return 0;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * @return list<string>
     */
    private function parseScopes(?string $value): array
    {
        if ($value === null) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $scope): string => trim($scope),
            explode(',', $value),
        ), static fn (string $scope): bool => $scope !== ''));
    }

    private function parseExpiresAt(?string $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable $exception) {
            throw new RuntimeException(sprintf('Invalid token expiration [%s].', $value), previous: $exception);
        }
    }
}
