<?php

declare(strict_types=1);

namespace Test\Unit\Providers;

use App\Docs\DocsCatalog;
use App\Docs\MarkdownRenderer;
use App\Providers\DocsServiceProvider;
use Myxa\Application;
use PHPUnit\Framework\Attributes\CoversClass;
use Test\TestCase;

#[CoversClass(DocsServiceProvider::class)]
final class DocsServiceProviderTest extends TestCase
{
    public function testProviderRegistersDocsCatalogAndMarkdownRendererBindings(): void
    {
        $app = new Application();

        $app->register(DocsServiceProvider::class);
        $app->boot();

        $catalog = $app->make(DocsCatalog::class);
        $renderer = $app->make(MarkdownRenderer::class);

        self::assertInstanceOf(DocsCatalog::class, $catalog);
        self::assertSame('getting-started', $catalog->defaultSlug());
        self::assertNotSame('', $renderer->render('# Docs'));
        self::assertInstanceOf(MarkdownRenderer::class, $renderer);
    }
}
