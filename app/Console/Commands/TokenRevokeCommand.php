<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Auth\TokenManager;
use App\Console\Exceptions\CommandFailedException;
use Myxa\Console\Command;
use Myxa\Console\InputArgument;

final class TokenRevokeCommand extends Command
{
    /**
     * Revoke bearer tokens by their primary key.
     */
    public function __construct(private readonly TokenManager $tokens)
    {
    }

    public function name(): string
    {
        return 'token:revoke';
    }

    public function description(): string
    {
        return 'Revoke a personal access token by ID.';
    }

    public function parameters(): array
    {
        return [
            new InputArgument('token', 'Token primary key.'),
        ];
    }

    protected function handle(): int
    {
        $tokenId = (int) $this->parameter('token');

        if (!$this->tokens->revoke($tokenId)) {
            $this->error(sprintf('Token [%d] was not found.', $tokenId))->icon();

            return 1;
        }

        $this->success(sprintf('Token [%d] revoked.', $tokenId))->icon();

        return 0;
    }
}
