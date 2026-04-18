<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\PersonalAccessToken;
use App\Models\User;
use DateTimeImmutable;
use JsonException;
use RuntimeException;

final class TokenManager
{
    /**
     * Issue, resolve, revoke, and prune bearer tokens tied to application users.
     */
    public function __construct(
        private readonly AuthConfig $config,
        private readonly UserManager $users,
    ) {
    }

    /**
     * Create a personal access token and return the plain-text token exactly once.
     *
     * @return array{token: PersonalAccessToken, plain_text_token: string}
     */
    public function issue(
        User|int|string $user,
        ?string $name = null,
        array $scopes = [],
        ?DateTimeImmutable $expiresAt = null,
    ): array {
        $resolvedUser = $this->users->resolveUser($user);
        $plainTextToken = $this->randomHex($this->config->tokenLength());
        $token = new PersonalAccessToken();
        $token->setAttribute('user_id', $resolvedUser->getKey());
        $token->setAttribute('name', $this->normalizeTokenName($name));
        $token->setAttribute('token_hash', hash('sha256', $plainTextToken));
        $token->setAttribute('scopes', $this->encodeScopes($this->normalizeScopes($scopes)));
        $token->setAttribute('expires_at', $expiresAt?->format(DATE_ATOM));
        $token->save();

        return [
            'token' => $token,
            'plain_text_token' => $plainTextToken,
        ];
    }

    /**
     * Resolve a valid personal access token from its plain-text bearer value.
     */
    public function resolve(string $plainTextToken): ?PersonalAccessToken
    {
        $plainTextToken = trim($plainTextToken);
        if ($plainTextToken === '') {
            return null;
        }

        $token = PersonalAccessToken::query()
            ->where('token_hash', '=', hash('sha256', $plainTextToken))
            ->first();

        if (!$token instanceof PersonalAccessToken || $token->revoked() || $token->expired()) {
            return null;
        }

        $token->setAttribute('last_used_at', (new DateTimeImmutable())->format(DATE_ATOM));
        $token->save();

        return $token;
    }

    /**
     * Return tokens for a user, or all tokens when no user filter is provided.
     *
     * @return list<PersonalAccessToken>
     */
    public function list(?User $user = null): array
    {
        $query = PersonalAccessToken::query()->orderBy('id', 'DESC');

        if ($user instanceof User && $user->getKey() !== null) {
            $query->where('user_id', '=', $user->getKey());
        }

        $tokens = $query->get();

        return array_values(array_filter(
            $tokens,
            static fn (mixed $token): bool => $token instanceof PersonalAccessToken,
        ));
    }

    /**
     * Revoke a token by its primary key.
     */
    public function revoke(int $tokenId): bool
    {
        $token = PersonalAccessToken::find($tokenId);
        if (!$token instanceof PersonalAccessToken) {
            return false;
        }

        if ($token->revoked()) {
            return true;
        }

        $token->setAttribute('revoked_at', (new DateTimeImmutable())->format(DATE_ATOM));

        return $token->save();
    }

    /**
     * Delete expired and revoked tokens from persistent storage.
     */
    public function prune(): int
    {
        $deleted = 0;

        foreach ($this->list() as $token) {
            if (!$token->revoked() && !$token->expired()) {
                continue;
            }

            if ($token->delete()) {
                $deleted++;
            }
        }

        return $deleted;
    }

    private function normalizeTokenName(?string $name): string
    {
        $name = $name !== null ? trim($name) : '';
        if ($name !== '') {
            return $name;
        }

        return $this->config->defaultTokenName();
    }

    /**
     * @param list<string> $scopes
     */
    private function encodeScopes(array $scopes): string
    {
        try {
            return json_encode($scopes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new RuntimeException('Failed to encode token scopes.', previous: $exception);
        }
    }

    /**
     * @param list<string> $scopes
     * @return list<string>
     */
    private function normalizeScopes(array $scopes): array
    {
        $normalized = array_values(array_filter(array_map(
            static fn (string $scope): string => trim($scope),
            $scopes,
        ), static fn (string $scope): bool => $scope !== ''));

        return $normalized !== [] ? $normalized : $this->config->defaultTokenScopes();
    }

    private function randomHex(int $length): string
    {
        $length = max(32, $length);
        $bytes = random_bytes((int) ceil($length / 2));

        return substr(bin2hex($bytes), 0, $length);
    }
}
