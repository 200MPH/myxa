<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use Myxa\Database\Attributes\Guarded;
use Myxa\Database\Attributes\Hidden;
use Myxa\Database\Model\HasTimestamps;
use Myxa\Database\Model\Model;
use Myxa\Database\Model\Relation;
use Throwable;

final class PersonalAccessToken extends Model
{
    use HasTimestamps;

    protected string $table = 'personal_access_tokens';

    protected ?int $id = null;

    protected ?int $user_id = null;

    protected string $name = '';

    #[Guarded]
    #[Hidden]
    protected ?string $token_hash = null;

    protected string $scopes = '[]';

    protected ?string $last_used_at = null;

    protected ?string $expires_at = null;

    protected ?string $revoked_at = null;

    /**
     * Return the user that owns this persisted bearer token.
     */
    public function user(): Relation
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Return the stored scope list for this token.
     *
     * @return list<string>
     */
    public function scopeList(): array
    {
        $decoded = json_decode($this->scopes !== '' ? $this->scopes : '[]', true);

        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $scope): string => is_string($scope) ? trim($scope) : '',
            $decoded,
        ), static fn (string $scope): bool => $scope !== ''));
    }

    /**
     * Determine whether this token grants a requested scope or wildcard pattern.
     */
    public function allowsScope(string $scope): bool
    {
        $scope = trim($scope);
        if ($scope === '') {
            return false;
        }

        foreach ($this->scopeList() as $pattern) {
            if ($pattern === '*' || $pattern === $scope) {
                return true;
            }

            $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#';
            if (preg_match($regex, $scope) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the token has already expired.
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
     * Determine whether the token has been revoked.
     */
    public function revoked(): bool
    {
        return $this->revoked_at !== null && trim($this->revoked_at) !== '';
    }
}
