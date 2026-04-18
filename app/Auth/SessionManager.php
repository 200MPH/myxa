<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\User;
use DateTimeImmutable;

final class SessionManager
{
    /**
     * Issue, resolve, and revoke persistent user sessions for the web guard.
     */
    public function __construct(
        private readonly AuthConfig $config,
        private readonly UserManager $users,
        private readonly SessionStoreInterface $store,
    ) {
    }

    /**
     * Create a new user session and return the plain-text session ID exactly once.
     *
     * @return array{session: SessionRecordInterface, plain_text_session: string}
     */
    public function issue(User|int|string $user, ?DateTimeImmutable $expiresAt = null): array
    {
        $resolvedUser = $this->users->resolveUser($user);
        $plainTextSession = $this->randomHex($this->config->sessionLength());
        $now = new DateTimeImmutable();
        $session = $this->store->issue(
            (int) $resolvedUser->getKey(),
            $plainTextSession,
            $expiresAt ?? new DateTimeImmutable('+' . $this->config->sessionLifetime() . ' seconds'),
            $now,
        );

        return [
            'session' => $session,
            'plain_text_session' => $plainTextSession,
        ];
    }

    /**
     * Resolve a valid session from its plain-text cookie value.
     */
    public function resolve(string $plainTextSession): ?SessionRecordInterface
    {
        $plainTextSession = trim($plainTextSession);
        if ($plainTextSession === '') {
            return null;
        }

        return $this->store->resolve($plainTextSession, new DateTimeImmutable());
    }

    /**
     * Revoke a session by its store-specific identifier.
     */
    public function revoke(int|string $sessionId): bool
    {
        return $this->store->revoke((string) $sessionId, new DateTimeImmutable());
    }

    private function randomHex(int $length): string
    {
        $length = max(32, $length);
        $bytes = random_bytes((int) ceil($length / 2));

        return substr(bin2hex($bytes), 0, $length);
    }
}
