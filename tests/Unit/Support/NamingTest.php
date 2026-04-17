<?php

declare(strict_types=1);

namespace Test\Unit\Support;

use App\Support\Naming;
use PHPUnit\Framework\Attributes\CoversClass;
use Test\TestCase;

#[CoversClass(Naming::class)]
final class NamingTest extends TestCase
{
    public function testClassBasenameSupportsQualifiedAndBareClasses(): void
    {
        self::assertSame('User', Naming::classBasename('App\\Models\\User'));
        self::assertSame('User', Naming::classBasename('\\App\\Models\\User'));
        self::assertSame('User', Naming::classBasename('User'));
    }

    public function testNamespaceReturnsTrailingNamespaceOrDefault(): void
    {
        self::assertSame('App\\Models', Naming::namespace('App\\Models\\User'));
        self::assertSame('App\\Models', Naming::namespace('User', 'App\\Models'));
        self::assertNull(Naming::namespace('User'));
    }

    public function testStudlyNormalizesMixedInputAndStripsLeadingDigits(): void
    {
        self::assertSame('SendDigestReport', Naming::studly('send_digest-report'));
        self::assertSame('UserLoggedIn', Naming::studly('  user logged in '));
        self::assertSame('AuditLog', Naming::studly('123-audit log'));
    }

    public function testSnakeNormalizesNamespacesWordsAndRepeatedSeparators(): void
    {
        self::assertSame('admin_audit_log', Naming::snake('Admin\\AuditLog'));
        self::assertSame('send_digest_report', Naming::snake('Send-Digest Report'));
        self::assertSame('', Naming::snake('   '));
    }

    public function testPluralizeAppliesCommonEnglishSuffixRules(): void
    {
        self::assertSame('categories', Naming::pluralize('category'));
        self::assertSame('boxes', Naming::pluralize('box'));
        self::assertSame('users', Naming::pluralize('user'));
        self::assertSame('', Naming::pluralize('   '));
    }
}
