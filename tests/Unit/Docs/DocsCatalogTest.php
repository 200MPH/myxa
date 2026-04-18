<?php

declare(strict_types=1);

namespace Test\Unit\Docs;

use App\Docs\DocsCatalog;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use Test\TestCase;

#[CoversClass(DocsCatalog::class)]
final class DocsCatalogTest extends TestCase
{
    private string $docsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->docsPath = sys_get_temp_dir() . '/myxa-docs-catalog-' . uniqid('', true);
        mkdir($this->docsPath, 0777, true);

        file_put_contents($this->docsPath . '/getting-started.md', "# Getting Started\n\nHello");
        file_put_contents($this->docsPath . '/queues.md', "# Queues\n\nQueue docs");
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->docsPath);

        parent::tearDown();
    }

    public function testCatalogListsPagesAndResolvesDefaultAndFind(): void
    {
        $catalog = new DocsCatalog($this->docsPath);

        self::assertSame('getting-started', $catalog->defaultSlug());
        self::assertSame(
            [
                ['slug' => 'getting-started', 'title' => 'Getting Started'],
                ['slug' => 'queues', 'title' => 'Queues'],
            ],
            $catalog->all(),
        );
        self::assertSame(
            [
                'slug' => 'queues',
                'title' => 'Queues',
                'markdown' => "# Queues\n\nQueue docs",
            ],
            $catalog->find('queues'),
        );
        self::assertNull($catalog->find('missing'));
    }

    public function testCatalogRejectsInvalidSlugs(): void
    {
        $catalog = new DocsCatalog($this->docsPath);

        $this->expectException(InvalidArgumentException::class);
        $catalog->find('../queues');
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
