<?php

declare(strict_types=1);

namespace App\Foundation {

    function file(string $filename, int $flags = 0): mixed
    {
        if (isset($GLOBALS['myxa.environment.file_override'])) {
            return $GLOBALS['myxa.environment.file_override']($filename, $flags);
        }

        return \file($filename, $flags);
    }
}

namespace Test\Unit\Foundation {

    use App\Foundation\Environment;
    use PHPUnit\Framework\Attributes\CoversClass;
    use Test\TestCase;

    #[CoversClass(Environment::class)]
    final class EnvironmentFileFailureTest extends TestCase
    {
        private string $environmentFile;

        protected function setUp(): void
        {
            parent::setUp();

            $this->environmentFile = sys_get_temp_dir() . '/myxa-env-failure-' . uniqid('', true) . '.env';
        }

        protected function tearDown(): void
        {
            unset($GLOBALS['myxa.environment.file_override']);

            if (is_file($this->environmentFile)) {
                unlink($this->environmentFile);
            }

            parent::tearDown();
        }

        public function testLoaderReturnsEarlyWhenUnderlyingFileReadFails(): void
        {
            $this->unsetEnvironmentValue('FAILED_READ_VALUE');

            file_put_contents($this->environmentFile, "FAILED_READ_VALUE=should-not-load\n");

            $environmentFile = $this->environmentFile;

            $GLOBALS['myxa.environment.file_override'] = static function (string $filename, int $flags) use ($environmentFile): false {
                self::assertSame($environmentFile, $filename);
                self::assertSame(FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES, $flags);

                return false;
            };

            Environment::load($this->environmentFile);

            self::assertFalse(getenv('FAILED_READ_VALUE'));
            self::assertArrayNotHasKey('FAILED_READ_VALUE', $_ENV);
            self::assertArrayNotHasKey('FAILED_READ_VALUE', $_SERVER);
        }
    }
}
