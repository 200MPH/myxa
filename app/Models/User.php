<?php

declare(strict_types=1);

namespace App\Models;

use Myxa\Database\Attributes\Guarded;
use Myxa\Database\Attributes\Hidden;
use Myxa\Database\Model\HasTimestamps;
use Myxa\Database\Model\Model;
use Myxa\Database\Model\Relation;

final class User extends Model
{
    use HasTimestamps;

    protected string $table = 'users';

    protected ?int $id = null;

    protected ?string $name = null;

    protected string $email = '';

    #[Guarded]
    #[Hidden]
    protected ?string $password_hash = null;

    /**
     * Return the persisted personal access tokens owned by the user.
     */
    public function tokens(): Relation
    {
        return $this->hasMany(PersonalAccessToken::class);
    }

    /**
     * Return the persisted web sessions owned by the user.
     */
    public function sessions(): Relation
    {
        return $this->hasMany(UserSession::class);
    }

    /**
     * Return the bearer token resolved for the current request, if any.
     */
    public function currentAccessToken(): ?PersonalAccessToken
    {
        $token = $this->getRelation('accessToken');

        return $token instanceof PersonalAccessToken ? $token : null;
    }

    /**
     * Return the session resolved for the current request, if any.
     */
    public function currentSession(): ?UserSession
    {
        $session = $this->getRelation('session');

        return $session instanceof UserSession ? $session : null;
    }

    /**
     * Determine whether the current resolved bearer token grants the given scope.
     */
    public function hasTokenScope(string $scope): bool
    {
        $token = $this->currentAccessToken();

        return $token instanceof PersonalAccessToken && $token->allowsScope($scope);
    }
}
