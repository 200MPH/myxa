<?php

declare(strict_types=1);

namespace App\Auth;

use RuntimeException;

final class PasswordHasher
{
    /**
     * Hash a plain-text password using PHP's current recommended algorithm.
     */
    public function hash(string $password): string
    {
        $password = trim($password);
        if ($password === '') {
            throw new RuntimeException('Password cannot be empty.');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        if (!is_string($hash)) {
            throw new RuntimeException('Password hashing failed.');
        }

        return $hash;
    }

    /**
     * Verify a plain-text password against a stored password hash.
     */
    public function verify(string $password, string $hash): bool
    {
        return $hash !== '' && password_verify($password, $hash);
    }
}
