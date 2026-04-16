<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\User;
use Myxa\Auth\SessionUserResolverInterface;
use Myxa\Http\Request;

final class SessionUserResolver implements SessionUserResolverInterface
{
    /**
     * Bridge the framework session guard to the app's persisted session store.
     */
    public function __construct(
        private readonly SessionManager $sessions,
        private readonly UserManager $users,
    ) {
    }

    /**
     * Return the authenticated user resolved from the session cookie, if any.
     */
    public function resolve(string $sessionId, Request $request): mixed
    {
        $session = $this->sessions->resolve($sessionId);
        if ($session === null) {
            return null;
        }

        $userId = $session->getAttribute('user_id');
        if (!is_int($userId)) {
            return null;
        }

        $user = $this->users->find($userId);
        if (!$user instanceof User) {
            return null;
        }

        $user->setRelation('session', $session);

        return $user;
    }
}
