<?php

declare(strict_types=1);

namespace Test\Unit\Console;

use App\Config\ConfigRepository;
use App\Console\Commands\VersionShowCommand;
use App\Console\Commands\VersionSyncCommand;
use App\Version\ApplicationVersion;
use App\Version\GitVersionResolver;
use Myxa\Console\ConsoleInput;
use Myxa\Console\ConsoleOutput;
use PHPUnit\Framework\Attributes\CoversClass;
use Test\TestCase;

#[CoversClass(VersionSyncCommand::class)]
#[CoversClass(VersionShowCommand::class)]
final class VersionCommandsTest extends TestCase
{
    private string $manifestPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manifestPath = sys_get_temp_dir() . '/myxa-version-command-' . uniqid('', true) . '.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->manifestPath)) {
            unlink($this->manifestPath);
        }

        parent::tearDown();
    }

    public function testVersionSyncCommandWritesManifestFromResolver(): void
    {
        $version = $this->makeVersionService();
        $resolver = new class extends GitVersionResolver {
            public function resolve(string $basePath): array
            {
                return [
                    'version' => 'v9.9.9',
                    'describe' => 'v9.9.9',
                    'tag' => 'v9.9.9',
                    'commit' => 'abcdef1234567890',
                    'short_commit' => 'abcdef1',
                    'dirty' => false,
                ];
            }
        };

        [$exitCode, $output] = $this->runCommand(new VersionSyncCommand($version, $resolver), 'version:sync');

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Version manifest synced from Git.', $output);
        self::assertFileExists($this->manifestPath);
        self::assertStringContainsString('v9.9.9', (string) file_get_contents($this->manifestPath));
    }

    public function testVersionShowCommandDisplaysCurrentManifestData(): void
    {
        $version = $this->makeVersionService();
        $version->writeGitManifest([
            'version' => 'v2.0.0',
            'describe' => 'v2.0.0',
            'tag' => 'v2.0.0',
            'commit' => 'fedcba9876543210',
            'short_commit' => 'fedcba9',
            'dirty' => false,
        ]);

        [$exitCode, $output] = $this->runCommand(new VersionShowCommand($version), 'version:show');

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('v2.0.0', $output);
        self::assertStringContainsString('git', $output);
        self::assertStringContainsString('fedcba9', $output);
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function runCommand(object $command, string $name): array
    {
        $stream = fopen('php://temp', 'w+b');
        self::assertIsResource($stream);

        $exitCode = $command->run(
            new ConsoleInput($name, [], []),
            new ConsoleOutput($stream, ansi: false),
        );

        rewind($stream);
        $output = stream_get_contents($stream);
        fclose($stream);

        return [$exitCode, is_string($output) ? $output : ''];
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
