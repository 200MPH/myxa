<?php

declare(strict_types=1);

namespace Test\Unit\Storage;

use App\Storage\StorageArea;
use PHPUnit\Framework\Attributes\CoversClass;
use Test\TestCase;

#[CoversClass(StorageArea::class)]
final class StorageAreaTest extends TestCase
{
    public function testPathBuildsPrefixedStorageLocations(): void
    {
        self::assertSame('public/avatars/jane.jpg', StorageArea::PublicArea->path('avatars/jane.jpg'));
        self::assertSame('private/reports/invoice.pdf', StorageArea::PrivateArea->path('/reports/invoice.pdf'));
    }
}
