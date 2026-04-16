<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Auth\TokenManager;
use Myxa\Console\Command;

final class TokenPruneCommand extends Command
{
    /**
     * Delete expired and revoked bearer tokens from storage.
     */
    public function __construct(private readonly TokenManager $tokens)
    {
    }

    public function name(): string
    {
        return 'token:prune';
    }

    public function description(): string
    {
        return 'Delete expired and revoked personal access tokens.';
    }

    protected function handle(): int
    {
        $deleted = $this->tokens->prune();

        $this->success(sprintf('Deleted %d token(s).', $deleted))->icon();

        return 0;
    }
}
