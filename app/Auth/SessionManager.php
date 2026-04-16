<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\User;
use App\Models\UserSession;
use DateTimeImmutable;

final class SessionManager
{
    /**
     * Issue, resolve, and revoke persistent user sessions for the web guard.
     */
    public function __construct(
        private readonly AuthConfig $config,
        private readonly UserManager $users,
    ) {
    }

    /**
     * Create a new user session and return the plain-text session ID exactly once.
     *
     * @return array{session: UserSession, plain_text_session: string}
     */
    public function issue(User|int|string $user, ?DateTimeImmutable $expiresAt = null): array
    {
        $resolvedUser = $this->users->resolveUser($user);
        $plainTextSession = $this->randomHex($this->config->sessionLength());
        $session = new UserSession();
        $session->setAttribute('user_id', $resolvedUser->getKey());
        $session->setAttribute('session_hash', hash('sha256', $plainTextSession));
        $session->setAttribute('last_used_at', (new DateTimeImmutable())->format(DATE_ATOM));
        $session->setAttribute(
            'expires_at',
            ($expiresAt ?? new DateTimeImmutable('+' . $this->config->sessionLifetime() . ' seconds'))->format(DATE_ATOM),
        );
        $session->save();

        return [
            'session' => $session,
            'plain_text_session' => $plainTextSession,
        ];
    }

    /**
     * Resolve a valid session from its plain-text cookie value.
     */
    public function resolve(string $plainTextSession): ?UserSession
    {
        $plainTextSession = trim($plainTextSession);
        if ($plainTextSession === '') {
            return null;
        }

        $session = UserSession::query()
            ->where('session_hash', '=', hash('sha256', $plainTextSession))
            ->first();

        if (!$session instanceof UserSession || $session->revoked() || $session->expired()) {
            return null;
        }

        $session->setAttribute('last_used_at', (new DateTimeImmutable())->format(DATE_ATOM));
        $session->save();

        return $session;
    }

    /**
     * Revoke a session by its numeric primary key.
     */
    public function revoke(int $sessionId): bool
    {
        $session = UserSession::find($sessionId);
        if (!$session instanceof UserSession) {
            return false;
        }

        if ($session->revoked()) {
            return true;
        }

        $session->setAttribute('revoked_at', (new DateTimeImmutable())->format(DATE_ATOM));

        return $session->save();
    }

    private function randomHex(int $length): string
    {
        $length = max(32, $length);
        $bytes = random_bytes((int) ceil($length / 2));

        return substr(bin2hex($bytes), 0, $length);
    }
}
