<?php

declare(strict_types=1);

namespace App\Storage;

enum StorageArea: string
{
    case PublicArea = 'public';
    case PrivateArea = 'private';

    public function path(string $location): string
    {
        return $this->value . '/' . ltrim($location, '/');
    }
}
