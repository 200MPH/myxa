<?php

declare(strict_types=1);

namespace App\Docs;

use InvalidArgumentException;
use RuntimeException;

final class DocsCatalog
{
    private readonly string $docsPath;

    public function __construct(string $docsPath)
    {
        $resolved = realpath($docsPath);

        if ($resolved === false || !is_dir($resolved)) {
            throw new InvalidArgumentException(sprintf('Docs path [%s] does not exist.', $docsPath));
        }

        $this->docsPath = rtrim($resolved, DIRECTORY_SEPARATOR);
    }

    /**
     * @return list<array{slug: string, title: string}>
     */
    public function all(): array
    {
        $paths = glob($this->docsPath . DIRECTORY_SEPARATOR . '*.md') ?: [];
        sort($paths);
        $order = [
            'getting-started' => 0,
            'configuration' => 1,
            'console-and-scaffolding' => 2,
            'http-routing' => 3,
            'auth' => 4,
            'validation' => 5,
            'rate-limiting' => 6,
            'database' => 7,
            'frontend' => 8,
            'queues' => 9,
            'cache-and-storage' => 10,
            'events-and-services' => 11,
        ];

        $pages = [];

        foreach ($paths as $path) {
            $slug = pathinfo($path, PATHINFO_FILENAME);

            if (!is_string($slug) || $slug === '') {
                continue;
            }

            $pages[] = [
                'slug' => $slug,
                'title' => $this->titleForPath($path, $slug),
            ];
        }

        usort(
            $pages,
            function (array $left, array $right) use ($order): int {
                $leftOrder = $order[$left['slug']] ?? 999;
                $rightOrder = $order[$right['slug']] ?? 999;

                if ($leftOrder !== $rightOrder) {
                    return $leftOrder <=> $rightOrder;
                }

                return strcmp($left['title'], $right['title']);
            },
        );

        return $pages;
    }

    /**
     * @return array{slug: string, title: string, markdown: string}|null
     */
    public function find(string $slug): ?array
    {
        $slug = $this->normalizeSlug($slug);
        $path = $this->docsPath . DIRECTORY_SEPARATOR . $slug . '.md';

        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $markdown = @file_get_contents($path);

        if (!is_string($markdown)) {
            throw new RuntimeException(sprintf('Unable to read docs page [%s].', $path));
        }

        return [
            'slug' => $slug,
            'title' => $this->titleForMarkdown($markdown, $slug),
            'markdown' => $markdown,
        ];
    }

    public function defaultSlug(): ?string
    {
        foreach ($this->all() as $page) {
            if ($page['slug'] === 'getting-started') {
                return $page['slug'];
            }
        }

        return $this->all()[0]['slug'] ?? null;
    }

    private function titleForPath(string $path, string $slug): string
    {
        $markdown = @file_get_contents($path);

        return is_string($markdown)
            ? $this->titleForMarkdown($markdown, $slug)
            : $this->titleFromSlug($slug);
    }

    private function titleForMarkdown(string $markdown, string $slug): string
    {
        $lines = preg_split('/\R/', $markdown) ?: [];

        foreach ($lines as $line) {
            if (preg_match('/^\#\s+(.+)$/', trim($line), $matches) === 1) {
                return trim($matches[1]);
            }
        }

        return $this->titleFromSlug($slug);
    }

    private function titleFromSlug(string $slug): string
    {
        return ucwords(str_replace('-', ' ', $slug));
    }

    private function normalizeSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));

        if ($slug === '' || preg_match('/^[a-z0-9-]+$/', $slug) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid docs page slug [%s].', $slug));
        }

        return $slug;
    }
}
