<?php

declare(strict_types=1);

namespace App\Docs;

use Myxa\Support\Html\Html;

final class MarkdownRenderer
{
    public function render(string $markdown): string
    {
        $lines = preg_split('/\R/', str_replace("\r\n", "\n", trim($markdown))) ?: [];
        $html = [];
        $count = count($lines);
        $index = 0;

        while ($index < $count) {
            $line = rtrim($lines[$index]);
            $trimmed = trim($line);

            if ($trimmed === '') {
                $index++;

                continue;
            }

            if (preg_match('/^```([a-z0-9_-]+)?\s*$/i', $trimmed, $matches) === 1) {
                $language = strtolower((string) ($matches[1] ?? ''));
                $buffer = [];
                $index++;

                while ($index < $count && trim($lines[$index]) !== '```') {
                    $buffer[] = rtrim($lines[$index], "\r");
                    $index++;
                }

                if ($index < $count) {
                    $index++;
                }

                $class = $language !== '' ? sprintf(' class="language-%s"', Html::escape($language)) : '';
                $html[] = sprintf(
                    '<pre><code%s>%s</code></pre>',
                    $class,
                    Html::escape(implode("\n", $buffer)),
                );

                continue;
            }

            if (preg_match('/^(#{1,6})\s+(.+)$/', $trimmed, $matches) === 1) {
                $level = strlen($matches[1]);
                $html[] = sprintf('<h%d>%s</h%d>', $level, $this->renderInline($matches[2]), $level);
                $index++;

                continue;
            }

            if (preg_match('/^>\s?(.*)$/', $trimmed, $matches) === 1) {
                $buffer = [$matches[1]];
                $index++;

                while ($index < $count && preg_match('/^>\s?(.*)$/', trim($lines[$index]), $nested) === 1) {
                    $buffer[] = $nested[1];
                    $index++;
                }

                $html[] = sprintf('<blockquote><p>%s</p></blockquote>', $this->renderInline(implode(' ', $buffer)));

                continue;
            }

            if (preg_match('/^[-*]\s+(.+)$/', $trimmed, $matches) === 1) {
                $items = [$matches[1]];
                $index++;

                while ($index < $count && preg_match('/^[-*]\s+(.+)$/', trim($lines[$index]), $nested) === 1) {
                    $items[] = $nested[1];
                    $index++;
                }

                $html[] = '<ul>' . implode('', array_map(
                    fn (string $item): string => sprintf('<li>%s</li>', $this->renderInline($item)),
                    $items,
                )) . '</ul>';

                continue;
            }

            if (preg_match('/^\d+\.\s+(.+)$/', $trimmed, $matches) === 1) {
                $items = [$matches[1]];
                $index++;

                while ($index < $count && preg_match('/^\d+\.\s+(.+)$/', trim($lines[$index]), $nested) === 1) {
                    $items[] = $nested[1];
                    $index++;
                }

                $html[] = '<ol>' . implode('', array_map(
                    fn (string $item): string => sprintf('<li>%s</li>', $this->renderInline($item)),
                    $items,
                )) . '</ol>';

                continue;
            }

            $buffer = [$trimmed];
            $index++;

            while ($index < $count) {
                $peek = trim($lines[$index]);

                if ($peek === '') {
                    break;
                }

                if (
                    preg_match('/^```/', $peek) === 1
                    || preg_match('/^(#{1,6})\s+/', $peek) === 1
                    || preg_match('/^>\s?/', $peek) === 1
                    || preg_match('/^[-*]\s+/', $peek) === 1
                    || preg_match('/^\d+\.\s+/', $peek) === 1
                ) {
                    break;
                }

                $buffer[] = $peek;
                $index++;
            }

            $html[] = sprintf('<p>%s</p>', $this->renderInline(implode(' ', $buffer)));
        }

        return implode("\n", $html);
    }

    private function renderInline(string $text): string
    {
        $segments = preg_split('/(`[^`]+`)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $rendered = '';

        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }

            if (str_starts_with($segment, '`') && str_ends_with($segment, '`')) {
                $rendered .= '<code>' . Html::escape(substr($segment, 1, -1)) . '</code>';

                continue;
            }

            $escaped = Html::escape($segment);
            $escaped = preg_replace_callback(
                '/\[([^\]]+)\]\(([^)]+)\)/',
                fn (array $matches): string => sprintf(
                    '<a href="%s">%s</a>',
                    Html::escape($this->transformLink(html_entity_decode(
                        $matches[2],
                        ENT_QUOTES | ENT_SUBSTITUTE,
                        'UTF-8',
                    ))),
                    $matches[1],
                ),
                $escaped,
            ) ?? $escaped;
            $escaped = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $escaped) ?? $escaped;
            $escaped = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $escaped) ?? $escaped;

            $rendered .= $escaped;
        }

        return $rendered;
    }

    private function transformLink(string $href): string
    {
        $href = trim($href);

        if ($href === '') {
            return '#';
        }

        if (str_contains($href, '://') || str_starts_with($href, '#') || str_starts_with($href, '/')) {
            return $href;
        }

        $parts = explode('#', $href, 2);
        $path = ltrim($parts[0], './');
        $anchor = isset($parts[1]) ? '#' . $parts[1] : '';

        if (str_ends_with($path, '.md')) {
            return '/docs/' . basename($path, '.md') . $anchor;
        }

        return $href;
    }
}
