<?php

declare(strict_types=1);

namespace Test\Unit\Version;

use App\Config\ConfigRepository;
use App\Version\ApplicationVersion;
use PHPUnit\Framework\Attributes\CoversClass;
use Test\TestCase;

#[CoversClass(ApplicationVersion::class)]
final class ApplicationVersionTest extends TestCase
{
    private string $manifestPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manifestPath = sys_get_temp_dir() . '/myxa-version-' . uniqid('', true) . '.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->manifestPath)) {
            unlink($this->manifestPath);
        }

        parent::tearDown();
    }

    public function testVersionFallsBackWhenManifestIsMissing(): void
    {
        $version = $this->makeVersionService();

        self::assertSame('dev', $version->current());
        self::assertSame('fallback', $version->source());
    }

    public function testVersionReadsStoredManifestDetails(): void
    {
        $version = $this->makeVersionService();

        $version->writeGitManifest([
            'version' => 'v1.2.3',
            'describe' => 'v1.2.3',
            'tag' => 'v1.2.3',
            'commit' => '0123456789abcdef',
            'short_commit' => '0123456',
            'dirty' => false,
        ]);

        $details = $version->details();

        self::assertSame('v1.2.3', $details['version']);
        self::assertSame('git', $details['source']);
        self::assertSame('v1.2.3', $details['tag']);
        self::assertSame('0123456', $details['short_commit']);
        self::assertFileExists($this->manifestPath);
    }

    private function makeVersionService(): ApplicationVersion
    {
        return new ApplicationVersion(new ConfigRepository([
            'version' => [
                'manifest' => $this->manifestPath,
                'fallback' => 'dev',
            ],
        ]));
    }
}
