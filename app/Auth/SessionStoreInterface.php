<?php

declare(strict_types=1);

namespace App\Auth;

use DateTimeImmutable;

interface SessionStoreInterface
{
    public function issue(
        int $userId,
        string $plainTextSession,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $now,
    ): SessionRecordInterface;

    public function resolve(string $plainTextSession, DateTimeImmutable $now): ?SessionRecordInterface;

    public function revoke(string $identifier, DateTimeImmutable $now): bool;
}
