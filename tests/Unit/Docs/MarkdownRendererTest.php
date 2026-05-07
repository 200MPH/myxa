<?php

declare(strict_types=1);

namespace Test\Unit\Docs;

use App\Docs\MarkdownRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use Test\TestCase;

#[CoversClass(MarkdownRenderer::class)]
final class MarkdownRendererTest extends TestCase
{
    public function testRendererConvertsBasicMarkdownToHtml(): void
    {
        $renderer = new MarkdownRenderer();

        $html = $renderer->render(<<<'MD'
# Getting Started

Use **Myxa** with `php`.

- First
- Second

1. Alpha
2. Beta

```php
echo 'hello';
```

See [Configuration](configuration.md).
MD);

        self::assertStringContainsString('<h1 id="getting-started">Getting Started</h1>', $html);
        self::assertStringContainsString('<strong>Myxa</strong>', $html);
        self::assertStringContainsString('<code>php</code>', $html);
        self::assertStringContainsString('<ul><li>First</li><li>Second</li></ul>', $html);
        self::assertStringContainsString('<ol><li>Alpha</li><li>Beta</li></ol>', $html);
        self::assertStringContainsString('<pre><code class="language-php">echo &#039;hello&#039;;</code></pre>', $html);
        self::assertStringContainsString('<a href="/docs/configuration">Configuration</a>', $html);
    }

    public function testRendererAddsStableHeadingIds(): void
    {
        $renderer = new MarkdownRenderer();

        $html = $renderer->render(<<<'MD'
# Docs

## When to Use `.env` vs Config Files

## When to Use `.env` vs Config Files
MD);

        self::assertStringContainsString(
            '<h2 id="when-to-use-env-vs-config-files">When to Use <code>.env</code> vs Config Files</h2>',
            $html,
        );
        self::assertStringContainsString(
            '<h2 id="when-to-use-env-vs-config-files-2">When to Use <code>.env</code> vs Config Files</h2>',
            $html,
        );
    }
}
