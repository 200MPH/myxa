<?php

declare(strict_types=1);

namespace Test\Unit\Auth;

use App\Auth\SessionRecord;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Models\UserSession;
use DateTimeImmutable;
use Myxa\Database\Model\Relation;
use PHPUnit\Framework\Attributes\CoversClass;
use Test\TestCase;

#[CoversClass(User::class)]
#[CoversClass(UserSession::class)]
#[CoversClass(PersonalAccessToken::class)]
final class AuthModelsTest extends TestCase
{
    public function testUserExposesCurrentRequestAuthContext(): void
    {
        $user = new User();
        $token = new PersonalAccessToken();
        $session = new SessionRecord(
            'session-id',
            123,
            'file',
            new DateTimeImmutable('-5 minutes'),
            new DateTimeImmutable('+1 hour'),
            null,
        );

        self::assertNull($user->currentAccessToken());
        self::assertNull($user->currentSession());
        self::assertFalse($user->hasTokenScope('users:read'));

        $token->setAttribute('scopes', '["users:*"]');
        $user->setRelation('accessToken', $token);
        $user->setRelation('session', $session);

        self::assertSame($token, $user->currentAccessToken());
        self::assertSame($session, $user->currentSession());
        self::assertTrue($user->hasTokenScope('users:read'));
        self::assertFalse($user->hasTokenScope('orders:read'));
        self::assertInstanceOf(Relation::class, $user->tokens());
        self::assertInstanceOf(Relation::class, $user->sessions());
    }

    public function testPersonalAccessTokenScopeListAndWildcardMatching(): void
    {
        $token = new PersonalAccessToken();
        $token->setAttribute('scopes', '[" users:read ", "users:*", 123, ""]');

        self::assertInstanceOf(Relation::class, $token->user());
        self::assertSame(['users:read', 'users:*'], $token->scopeList());
        self::assertTrue($token->allowsScope('users:read'));
        self::assertTrue($token->allowsScope('users:write'));
        self::assertFalse($token->allowsScope(''));
        self::assertFalse($token->allowsScope('orders:read'));
    }

    public function testPersonalAccessTokenHandlesInvalidState(): void
    {
        $token = new PersonalAccessToken();
        $token->setAttribute('scopes', '{invalid json');
        $token->setAttribute('expires_at', 'not-a-date');
        $token->setAttribute('revoked_at', '   ');

        self::assertSame([], $token->scopeList());
        self::assertFalse($token->expired());
        self::assertFalse($token->revoked());

        $token->setAttribute('expires_at', (new DateTimeImmutable('-1 minute'))->format(DATE_ATOM));
        $token->setAttribute('revoked_at', (new DateTimeImmutable('now'))->format(DATE_ATOM));

        self::assertTrue($token->expired(new DateTimeImmutable('now')));
        self::assertTrue($token->revoked());
    }

    public function testUserSessionExposesDatesAndStateFlags(): void
    {
        $session = new UserSession();
        $session->setAttribute('user_id', 42);
        $session->setAttribute('last_used_at', (new DateTimeImmutable('-5 minutes'))->format(DATE_ATOM));
        $session->setAttribute('expires_at', (new DateTimeImmutable('+1 hour'))->format(DATE_ATOM));

        self::assertInstanceOf(Relation::class, $session->user());
        self::assertSame('', $session->identifier());
        self::assertSame(42, $session->userId());
        self::assertSame('database', $session->driver());
        self::assertInstanceOf(DateTimeImmutable::class, $session->lastUsedAt());
        self::assertInstanceOf(DateTimeImmutable::class, $session->expiresAt());
        self::assertNull($session->revokedAt());
        self::assertFalse($session->expired(new DateTimeImmutable('now')));
        self::assertFalse($session->revoked());
    }

    public function testUserSessionHandlesInvalidAndRevokedState(): void
    {
        $session = new UserSession();
        $session->setAttribute('expires_at', 'not-a-date');
        $session->setAttribute('last_used_at', '');
        $session->setAttribute('revoked_at', 'invalid-date');

        self::assertNull($session->lastUsedAt());
        self::assertFalse($session->expired());
        self::assertNull($session->revokedAt());
        self::assertTrue($session->revoked());

        $session->setAttribute('expires_at', (new DateTimeImmutable('-1 minute'))->format(DATE_ATOM));

        self::assertTrue($session->expired(new DateTimeImmutable('now')));
    }
}
