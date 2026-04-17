<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\User;
use Myxa\Http\Request;
use Myxa\Auth\SessionUserResolverInterface;

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

        $user = $this->users->find($session->userId());
        if (!$user instanceof User) {
            return null;
        }

        $user->setRelation('session', $session);

        return $user;
    }
}
