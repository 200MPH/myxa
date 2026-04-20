<?php

declare(strict_types=1);

namespace Test\Unit\Support;

use App\Config\ConfigRepository;
use App\Support\Facades\Config;
use App\Support\Facades\PublicFile;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use Test\TestCase;

#[CoversClass(PublicFile::class)]
final class PublicFileTest extends TestCase
{
    public function testUrlBuildsAbsolutePublicStorageUrl(): void
    {
        Config::setRepository(new ConfigRepository([
            'app' => [
                'url' => 'https://example.com/',
            ],
        ]));

        self::assertSame(
            'https://example.com/storage/documents/report.pdf',
            PublicFile::url('/documents/report.pdf'),
        );
    }

    public function testUrlRejectsInvalidTraversalSegments(): void
    {
        Config::setRepository(new ConfigRepository([
            'app' => [
                'url' => 'https://example.com',
            ],
        ]));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File location cannot contain traversal segments.');

        PublicFile::url('../secrets.txt');
    }
}
