<?php

declare(strict_types=1);

namespace App\Version;

use App\Config\ConfigRepository;
use RuntimeException;

final class ApplicationVersion
{
    public function __construct(private readonly ConfigRepository $config)
    {
    }

    public function current(): string
    {
        $manifest = $this->manifest();
        $version = $manifest['version'] ?? null;

        if (is_string($version) && trim($version) !== '') {
            return trim($version);
        }

        $envVersion = env('APP_VERSION');

        if (is_string($envVersion) && trim($envVersion) !== '') {
            return trim($envVersion);
        }

        return (string) $this->config->get('version.fallback', 'dev');
    }

    public function source(): string
    {
        $manifest = $this->manifest();
        $source = $manifest['source'] ?? null;

        if (is_string($source) && trim($source) !== '') {
            return trim($source);
        }

        $envVersion = env('APP_VERSION');

        return is_string($envVersion) && trim($envVersion) !== ''
            ? 'env'
            : 'fallback';
    }

    /**
     * @return array{
     *     version: string,
     *     source: string,
     *     generated_at: string|null,
     *     tag: string|null,
     *     describe: string|null,
     *     commit: string|null,
     *     short_commit: string|null,
     *     dirty: bool|null
     * }
     */
    public function details(): array
    {
        $manifest = $this->manifest();

        return [
            'version' => $this->current(),
            'source' => $this->source(),
            'generated_at' => $this->stringValue($manifest['generated_at'] ?? null),
            'tag' => $this->stringValue($manifest['tag'] ?? null),
            'describe' => $this->stringValue($manifest['describe'] ?? null),
            'commit' => $this->stringValue($manifest['commit'] ?? null),
            'short_commit' => $this->stringValue($manifest['short_commit'] ?? null),
            'dirty' => is_bool($manifest['dirty'] ?? null) ? $manifest['dirty'] : null,
        ];
    }

    public function manifestPath(): string
    {
        return (string) $this->config->get('version.manifest', base_path('version.json'));
    }

    /**
     * @param array{
     *     version: string,
     *     describe: string,
     *     tag: string|null,
     *     commit: string,
     *     short_commit: string,
     *     dirty: bool
     * } $gitMetadata
     * @return array<string, mixed>
     */
    public function writeGitManifest(array $gitMetadata): array
    {
        $payload = [
            'version' => $gitMetadata['version'],
            'source' => 'git',
            'generated_at' => gmdate(DATE_ATOM),
            'tag' => $gitMetadata['tag'],
            'describe' => $gitMetadata['describe'],
            'commit' => $gitMetadata['commit'],
            'short_commit' => $gitMetadata['short_commit'],
            'dirty' => $gitMetadata['dirty'],
        ];

        $this->writeManifest($payload);

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function manifest(): array
    {
        $contents = @file_get_contents($this->manifestPath());

        if (!is_string($contents) || trim($contents) === '') {
            return [];
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writeManifest(array $payload): void
    {
        $directory = dirname($this->manifestPath());

        if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf(
                'Unable to create version manifest directory [%s].',
                $directory,
            ));
        }

        $encoded = json_encode($payload, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR);

        if (!is_string($encoded) || @file_put_contents($this->manifestPath(), $encoded . PHP_EOL, \LOCK_EX) === false) {
            throw new RuntimeException(sprintf(
                'Unable to write version manifest [%s].',
                $this->manifestPath(),
            ));
        }
    }

    private function stringValue(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
