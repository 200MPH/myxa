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

        self::assertStringContainsString('<h1>Getting Started</h1>', $html);
        self::assertStringContainsString('<strong>Myxa</strong>', $html);
        self::assertStringContainsString('<code>php</code>', $html);
        self::assertStringContainsString('<ul><li>First</li><li>Second</li></ul>', $html);
        self::assertStringContainsString('<ol><li>Alpha</li><li>Beta</li></ol>', $html);
        self::assertStringContainsString('<pre><code class="language-php">echo &#039;hello&#039;;</code></pre>', $html);
        self::assertStringContainsString('<a href="/docs/configuration">Configuration</a>', $html);
    }
}
