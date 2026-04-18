<?php

declare(strict_types=1);

namespace App\Foundation;

final class ConfigLoader
{
    /**
     * @return array<string, mixed>
     */
    public static function load(string $configDirectory): array
    {
        $files = glob($configDirectory . '/*.php') ?: [];
        sort($files);

        $configuration = [];

        foreach ($files as $file) {
            $key = basename($file, '.php');
            $loaded = require $file;

            $configuration[$key] = is_array($loaded) ? $loaded : [];
        }

        return $configuration;
    }
}
