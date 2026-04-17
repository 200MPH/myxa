<?php

declare(strict_types=1);

namespace App\Auth {
    function password_hash(string $password, string|int|null $algo, array $options = []): mixed
    {
        $override = $GLOBALS['myxa_test_password_hash_override'] ?? null;

        if (is_callable($override)) {
            /** @var callable(string, string|int|null, array): mixed $override */
            return $override($password, $algo, $options);
        }

        return \password_hash($password, $algo, $options);
    }

    function password_verify(string $password, string $hash): bool
    {
        $override = $GLOBALS['myxa_test_password_verify_override'] ?? null;

        if (is_callable($override)) {
            /** @var callable(string, string): bool $override */
            return $override($password, $hash);
        }

        return \password_verify($password, $hash);
    }
}

namespace Test\Unit\Auth {
    use App\Auth\PasswordHasher;
    use PHPUnit\Framework\Attributes\CoversClass;
    use Test\TestCase;

    #[CoversClass(PasswordHasher::class)]
    final class PasswordHasherTest extends TestCase
    {
        protected function setUp(): void
        {
            parent::setUp();

            unset(
                $GLOBALS['myxa_test_password_hash_override'],
                $GLOBALS['myxa_test_password_verify_override'],
            );
        }

        protected function tearDown(): void
        {
            unset(
                $GLOBALS['myxa_test_password_hash_override'],
                $GLOBALS['myxa_test_password_verify_override'],
            );

            parent::tearDown();
        }

        public function testVerifyDelegatesToUnderlyingPasswordVerify(): void
        {
            $hasher = new PasswordHasher();
            $seen = [];

            $GLOBALS['myxa_test_password_verify_override'] = static function (string $password, string $hash) use (&$seen): bool {
                $seen = [$password, $hash];

                return $password === 'secret-123' && $hash === 'hashed-value';
            };

            self::assertTrue($hasher->verify('secret-123', 'hashed-value'));
            self::assertSame(['secret-123', 'hashed-value'], $seen);
            self::assertFalse($hasher->verify('wrong', 'hashed-value'));
        }

        public function testHashThrowsWhenUnderlyingPasswordHashFails(): void
        {
            $hasher = new PasswordHasher();

            $GLOBALS['myxa_test_password_hash_override'] = static fn (): false => false;

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Password hashing failed.');

            $hasher->hash('secret-123');
        }
    }
}
