<?php

declare(strict_types=1);

namespace Test\Unit\Data;

use App\Data\DataScaffolder;
use PHPUnit\Framework\Attributes\CoversClass;
use Test\TestCase;

#[CoversClass(DataScaffolder::class)]
final class DataScaffolderTest extends TestCase
{
    private string $dataPath;

    protected function setUp(): void
    {
        parent::setUp();

        $rootPath = sys_get_temp_dir() . '/myxa-data-scaffolder-' . uniqid('', true);
        $this->dataPath = $rootPath . '/app/Data';

        mkdir($this->dataPath, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory(dirname(dirname(dirname($this->dataPath))));

        parent::tearDown();
    }

    public function testMakeCreatesDefaultDataClass(): void
    {
        $scaffolder = new DataScaffolder($this->dataPath);

        $result = $scaffolder->make('User');
        $source = file_get_contents($result['path']);

        self::assertSame($this->dataPath . '/UserData.php', $result['path']);
        self::assertSame('App\\Data\\UserData', $result['class']);
        self::assertIsString($source);
        self::assertStringContainsString('final readonly class UserData implements JsonSerializable', $source);
        self::assertStringContainsString('public function __construct(public array $attributes = [])', $source);
        self::assertStringContainsString('public static function fromArray(array $attributes): self', $source);
        self::assertStringContainsString('public function toArray(): array', $source);
    }

    public function testMakeCreatesNestedDataClass(): void
    {
        $scaffolder = new DataScaffolder($this->dataPath);

        $result = $scaffolder->make('Auth\\LoginData');
        $source = file_get_contents($result['path']);

        self::assertSame($this->dataPath . '/Auth/LoginData.php', $result['path']);
        self::assertSame('App\\Data\\Auth\\LoginData', $result['class']);
        self::assertIsString($source);
        self::assertStringContainsString('namespace App\\Data\\Auth;', $source);
    }

    public function testMakeAcceptsSlashDelimitedDataNames(): void
    {
        $scaffolder = new DataScaffolder($this->dataPath);

        $result = $scaffolder->make('Users/Profile');
        $source = file_get_contents($result['path']);

        self::assertSame($this->dataPath . '/Users/ProfileData.php', $result['path']);
        self::assertSame('App\\Data\\Users\\ProfileData', $result['class']);
        self::assertIsString($source);
        self::assertStringContainsString('namespace App\\Data\\Users;', $source);
        self::assertStringContainsString('final readonly class ProfileData implements JsonSerializable', $source);
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

            unlink($child);
        }

        rmdir($path);
    }
}
