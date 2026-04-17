<?php

declare(strict_types=1);

namespace App\Auth;

use DateTimeImmutable;

final readonly class SessionRecord implements SessionRecordInterface
{
    public function __construct(
        private string $identifier,
        private int $userId,
        private string $driver,
        private ?DateTimeImmutable $lastUsedAt,
        private ?DateTimeImmutable $expiresAt,
        private ?DateTimeImmutable $revokedAt = null,
    ) {
    }

    public function identifier(): string
    {
        return $this->identifier;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function driver(): string
    {
        return $this->driver;
    }

    public function lastUsedAt(): ?DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function expiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function revokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function expired(?DateTimeImmutable $now = null): bool
    {
        return $this->expiresAt !== null && $this->expiresAt <= ($now ?? new DateTimeImmutable());
    }

    public function revoked(): bool
    {
        return $this->revokedAt !== null;
    }
}
