<?php

declare(strict_types=1);

namespace Test\Unit\Foundation;

use App\Foundation\ConfigLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use Test\TestCase;

#[CoversClass(ConfigLoader::class)]
final class ConfigLoaderTest extends TestCase
{
    private string $configPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configPath = sys_get_temp_dir() . '/myxa-config-' . uniqid('', true);
        mkdir($this->configPath, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->configPath . '/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        if (is_dir($this->configPath)) {
            rmdir($this->configPath);
        }

        parent::tearDown();
    }

    public function testLoaderReturnsSortedConfigurationArraysAndNormalizesNonArrays(): void
    {
        file_put_contents($this->configPath . '/services.php', <<<'PHP'
<?php
return ['redis' => ['default' => 'cache']];
PHP);

        file_put_contents($this->configPath . '/app.php', <<<'PHP'
<?php
return ['name' => 'Myxa'];
PHP);

        file_put_contents($this->configPath . '/invalid.php', <<<'PHP'
<?php
return 'oops';
PHP);

        $loaded = ConfigLoader::load($this->configPath);

        self::assertSame(['app', 'invalid', 'services'], array_keys($loaded));
        self::assertSame(['name' => 'Myxa'], $loaded['app']);
        self::assertSame([], $loaded['invalid']);
        self::assertSame(['redis' => ['default' => 'cache']], $loaded['services']);
    }
}
