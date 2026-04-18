<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\User;
use Myxa\Auth\BearerTokenResolverInterface;
use Myxa\Http\Request;

final class BearerTokenResolver implements BearerTokenResolverInterface
{
    /**
     * Bridge the framework bearer guard to the app's token and user storage.
     */
    public function __construct(
        private readonly TokenManager $tokens,
        private readonly UserManager $users,
    ) {
    }

    /**
     * Return the authenticated user resolved from the bearer token, if any.
     */
    public function resolve(string $token, Request $request): mixed
    {
        $accessToken = $this->tokens->resolve($token);
        if ($accessToken === null) {
            return null;
        }

        $userId = $accessToken->getAttribute('user_id');
        if (!is_int($userId)) {
            return null;
        }

        $user = $this->users->find($userId);
        if (!$user instanceof User) {
            return null;
        }

        $user->setRelation('accessToken', $accessToken);

        return $user;
    }
}
