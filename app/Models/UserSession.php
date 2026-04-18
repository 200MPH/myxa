<?php

declare(strict_types=1);

namespace App\Models;

use App\Auth\SessionRecordInterface;
use DateTimeImmutable;
use Myxa\Database\Attributes\Guarded;
use Myxa\Database\Attributes\Hidden;
use Myxa\Database\Model\HasTimestamps;
use Myxa\Database\Model\Model;
use Myxa\Database\Model\Relation;
use Throwable;

final class UserSession extends Model implements SessionRecordInterface
{
    use HasTimestamps;

    protected string $table = 'user_sessions';

    protected ?int $id = null;

    protected ?int $user_id = null;

    #[Guarded]
    #[Hidden]
    protected ?string $session_hash = null;

    protected ?string $last_used_at = null;

    protected ?string $expires_at = null;

    protected ?string $revoked_at = null;

    /**
     * Return the user that owns this persisted session.
     */
    public function user(): Relation
    {
        return $this->belongsTo(User::class);
    }

    public function identifier(): string
    {
        return (string) ($this->getKey() ?? '');
    }

    public function userId(): int
    {
        return (int) ($this->user_id ?? 0);
    }

    public function driver(): string
    {
        return 'database';
    }

    public function lastUsedAt(): ?DateTimeImmutable
    {
        return $this->date($this->last_used_at);
    }

    public function expiresAt(): ?DateTimeImmutable
    {
        return $this->date($this->expires_at);
    }

    public function revokedAt(): ?DateTimeImmutable
    {
        return $this->date($this->revoked_at);
    }

    /**
     * Determine whether the session has already expired.
     */
    public function expired(?DateTimeImmutable $now = null): bool
    {
        if ($this->expires_at === null || trim($this->expires_at) === '') {
            return false;
        }

        try {
            $expiresAt = new DateTimeImmutable($this->expires_at);
        } catch (Throwable) {
            return false;
        }

        return $expiresAt <= ($now ?? new DateTimeImmutable());
    }

    /**
     * Determine whether the session has been revoked.
     */
    public function revoked(): bool
    {
        return $this->revoked_at !== null && trim($this->revoked_at) !== '';
    }

    private function date(?string $value): ?DateTimeImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }
}
