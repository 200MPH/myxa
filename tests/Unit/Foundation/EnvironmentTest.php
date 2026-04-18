<?php

declare(strict_types=1);

namespace Test\Unit\Foundation;

use App\Foundation\Environment;
use PHPUnit\Framework\Attributes\CoversClass;
use Test\TestCase;

#[CoversClass(Environment::class)]
final class EnvironmentTest extends TestCase
{
    private string $environmentFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->environmentFile = sys_get_temp_dir() . '/myxa-env-' . uniqid('', true) . '.env';
    }

    protected function tearDown(): void
    {
        if (is_file($this->environmentFile)) {
            unlink($this->environmentFile);
        }

        parent::tearDown();
    }

    public function testLoaderParsesQuotedExportedAndCommentedValues(): void
    {
        foreach (['APP_NAME', 'APP_ENV', 'APP_DEBUG', 'APP_DSN', 'APP_QUOTED', 'EXISTING_VALUE'] as $name) {
            $this->unsetEnvironmentValue($name);
        }

        $this->setEnvironmentValue('EXISTING_VALUE', 'keep-me');

        file_put_contents($this->environmentFile, <<<'ENV'
# comment
APP_NAME=Myxa
export APP_ENV="local"
APP_DEBUG=true # inline comment
APP_DSN=mysql://db
APP_QUOTED='quoted value'
INVALID_LINE
EXISTING_VALUE=override-me
ENV);

        Environment::load($this->environmentFile);

        self::assertSame('Myxa', getenv('APP_NAME'));
        self::assertSame('local', $_ENV['APP_ENV']);
        self::assertSame('true', $_SERVER['APP_DEBUG']);
        self::assertSame('mysql://db', getenv('APP_DSN'));
        self::assertSame('quoted value', getenv('APP_QUOTED'));
        self::assertSame('keep-me', getenv('EXISTING_VALUE'));
    }

    public function testLoaderIgnoresMissingFiles(): void
    {
        $this->unsetEnvironmentValue('UNSET_VALUE');

        Environment::load($this->environmentFile . '.missing');

        self::assertFalse(getenv('UNSET_VALUE'));
    }

    public function testLoaderIgnoresEntriesWithBlankVariableNames(): void
    {
        $this->unsetEnvironmentValue('VALID_VALUE');

        file_put_contents($this->environmentFile, <<<'ENV'
=missing-name
VALID_VALUE=present
ENV);

        Environment::load($this->environmentFile);

        self::assertFalse(getenv(''));
        self::assertSame('present', getenv('VALID_VALUE'));
    }
}
