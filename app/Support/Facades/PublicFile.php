<?php

declare(strict_types=1);

namespace App\Support\Facades;

use Myxa\Storage\StoragePath;

final class PublicFile
{
    /**
     * Build an absolute public URL for a file stored on the public disk.
     */
    public static function url(string $location): string
    {
        return sprintf(
            '%s/storage/%s',
            rtrim((string) Config::get('app.url'), '/'),
            StoragePath::normalizeLocation($location),
        );
    }
}
