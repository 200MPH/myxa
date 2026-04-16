<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\User;
use RuntimeException;

final class UserManager
{
    /**
     * Create and maintain user records used by web sessions and API tokens.
     */
    public function __construct(private readonly PasswordHasher $passwords)
    {
    }

    /**
     * Create a new user with a hashed password.
     */
    public function create(string $email, string $password, ?string $name = null): User
    {
        $email = $this->normalizeEmail($email);
        $name = $name !== null ? trim($name) : null;

        if ($this->find($email) instanceof User) {
            throw new RuntimeException(sprintf('User [%s] already exists.', $email));
        }

        $user = new User();
        $user->setAttribute('email', $email);
        $user->setAttribute('name', $name !== '' ? $name : null);
        $user->setAttribute('password_hash', $this->passwords->hash($password));
        $user->save();

        return $user;
    }

    /**
     * Change the password for an existing user.
     */
    public function changePassword(User|int|string $user, string $password): User
    {
        $resolved = $this->resolveUser($user);
        $resolved->setAttribute('password_hash', $this->passwords->hash($password));
        $resolved->save();

        return $resolved;
    }

    /**
     * Find a user by numeric ID or email address.
     */
    public function find(int|string $identifier): ?User
    {
        if (is_int($identifier) || ctype_digit(trim((string) $identifier))) {
            $user = User::find((int) $identifier);

            return $user instanceof User ? $user : null;
        }

        $user = User::query()
            ->where('email', '=', $this->normalizeEmail((string) $identifier))
            ->first();

        return $user instanceof User ? $user : null;
    }

    /**
     * Return users ordered by their primary key.
     *
     * @return list<User>
     */
    public function list(?int $limit = 100): array
    {
        $users = User::query()
            ->orderBy('id')
            ->limit($limit)
            ->get();

        return array_values(array_filter($users, static fn (mixed $user): bool => $user instanceof User));
    }

    /**
     * Verify a candidate password against a user's stored password hash.
     */
    public function verifyPassword(User $user, string $password): bool
    {
        $hash = $user->getAttribute('password_hash');

        return is_string($hash) && $this->passwords->verify($password, $hash);
    }

    /**
     * Resolve a required user or throw a descriptive exception.
     */
    public function resolveUser(User|int|string $user): User
    {
        if ($user instanceof User) {
            return $user;
        }

        $resolved = $this->find($user);
        if (!$resolved instanceof User) {
            throw new RuntimeException(sprintf('User [%s] was not found.', (string) $user));
        }

        return $resolved;
    }

    private function normalizeEmail(string $email): string
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            throw new RuntimeException('Email cannot be empty.');
        }

        return $email;
    }
}
