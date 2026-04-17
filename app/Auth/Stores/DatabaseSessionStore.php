<?php

declare(strict_types=1);

namespace App\Auth\Stores;

use App\Auth\SessionRecordInterface;
use App\Auth\SessionStoreInterface;
use App\Models\UserSession;
use DateTimeImmutable;

final class DatabaseSessionStore implements SessionStoreInterface
{
    public function issue(
        int $userId,
        string $plainTextSession,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $now,
    ): SessionRecordInterface {
        $session = new UserSession();
        $session->setAttribute('user_id', $userId);
        $session->setAttribute('session_hash', hash('sha256', $plainTextSession));
        $session->setAttribute('last_used_at', $now->format(DATE_ATOM));
        $session->setAttribute('expires_at', $expiresAt->format(DATE_ATOM));
        $session->save();

        return $session;
    }

    public function resolve(string $plainTextSession, DateTimeImmutable $now): ?SessionRecordInterface
    {
        $session = UserSession::query()
            ->where('session_hash', '=', hash('sha256', $plainTextSession))
            ->first();

        if (!$session instanceof UserSession || $session->revoked() || $session->expired($now)) {
            return null;
        }

        $session->setAttribute('last_used_at', $now->format(DATE_ATOM));
        $session->save();

        return $session;
    }

    public function revoke(string $identifier, DateTimeImmutable $now): bool
    {
        if (!ctype_digit($identifier)) {
            return false;
        }

        $session = UserSession::find((int) $identifier);
        if (!$session instanceof UserSession) {
            return false;
        }

        if ($session->revoked()) {
            return true;
        }

        $session->setAttribute('revoked_at', $now->format(DATE_ATOM));

        return $session->save();
    }
}
