<?php

declare(strict_types=1);

namespace App\Auth;

use DateTimeImmutable;

interface SessionRecordInterface
{
    public function identifier(): string;

    public function userId(): int;

    public function driver(): string;

    public function lastUsedAt(): ?DateTimeImmutable;

    public function expiresAt(): ?DateTimeImmutable;

    public function revokedAt(): ?DateTimeImmutable;

    public function expired(?DateTimeImmutable $now = null): bool;

    public function revoked(): bool;
}
