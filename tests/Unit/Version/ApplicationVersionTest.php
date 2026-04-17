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

    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryDirectory = sys_get_temp_dir() . '/myxa-version-tests-' . uniqid('', true);
        mkdir($this->temporaryDirectory, 0777, true);
        $this->manifestPath = sys_get_temp_dir() . '/myxa-version-' . uniqid('', true) . '.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->manifestPath)) {
            unlink($this->manifestPath);
        }

        $this->removeDirectory($this->temporaryDirectory);

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

    public function testVersionUsesEnvironmentFallbackWhenManifestHasNoVersion(): void
    {
        $this->setEnvironmentValue('APP_VERSION', 'v-env-1.0.0');
        file_put_contents($this->manifestPath, json_encode(['source' => ''], JSON_THROW_ON_ERROR));

        $version = $this->makeVersionService();

        self::assertSame('v-env-1.0.0', $version->current());
        self::assertSame('env', $version->source());
    }

    public function testVersionIgnoresInvalidManifestJson(): void
    {
        file_put_contents($this->manifestPath, '{invalid json');

        $version = $this->makeVersionService();

        self::assertSame([], $version->manifest());
        self::assertSame('dev', $version->current());
        self::assertSame('fallback', $version->source());
    }

    public function testManifestPathFallsBackToProjectVersionFileWhenConfigMissing(): void
    {
        $version = new ApplicationVersion(new ConfigRepository([]));

        self::assertStringEndsWith('version.json', $version->manifestPath());
    }

    public function testDetailsNormalizesBlankManifestValuesToNull(): void
    {
        file_put_contents($this->manifestPath, json_encode([
            'version' => 'v2.0.0',
            'source' => 'git',
            'generated_at' => '   ',
            'tag' => '',
            'describe' => '   ',
            'commit' => null,
            'short_commit' => '',
            'dirty' => 'nope',
        ], JSON_THROW_ON_ERROR));

        $details = $this->makeVersionService()->details();

        self::assertSame('v2.0.0', $details['version']);
        self::assertSame('git', $details['source']);
        self::assertNull($details['generated_at']);
        self::assertNull($details['tag']);
        self::assertNull($details['describe']);
        self::assertNull($details['commit']);
        self::assertNull($details['short_commit']);
        self::assertNull($details['dirty']);
    }

    public function testWriteGitManifestThrowsWhenManifestDirectoryCannotBeCreated(): void
    {
        $blockingFile = $this->temporaryDirectory . '/occupied-parent';
        file_put_contents($blockingFile, 'occupied');

        $version = new ApplicationVersion(new ConfigRepository([
            'version' => [
                'manifest' => $blockingFile . '/version.json',
                'fallback' => 'dev',
            ],
        ]));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to create version manifest directory');

        $version->writeGitManifest([
            'version' => 'v1.0.0',
            'describe' => 'v1.0.0',
            'tag' => 'v1.0.0',
            'commit' => '0123456789abcdef',
            'short_commit' => '0123456',
            'dirty' => false,
        ]);
    }

    public function testWriteGitManifestThrowsWhenManifestCannotBeWritten(): void
    {
        $manifestDirectory = $this->temporaryDirectory . '/manifest-as-directory';
        mkdir($manifestDirectory, 0777, true);

        $version = new ApplicationVersion(new ConfigRepository([
            'version' => [
                'manifest' => $manifestDirectory,
                'fallback' => 'dev',
            ],
        ]));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to write version manifest');

        $version->writeGitManifest([
            'version' => 'v1.0.1',
            'describe' => 'v1.0.1',
            'tag' => 'v1.0.1',
            'commit' => 'fedcba9876543210',
            'short_commit' => 'fedcba9',
            'dirty' => false,
        ]);
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

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $child = $path . '/' . $entry;

            if (is_dir($child)) {
                $this->removeDirectory($child);

                continue;
            }

            @unlink($child);
        }

        @rmdir($path);
    }
}
