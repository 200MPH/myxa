<?php

declare(strict_types=1);

namespace App\Providers;

use App\Docs\DocsCatalog;
use App\Docs\MarkdownRenderer;
use Myxa\Support\ServiceProvider;

final class DocsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app()->singleton(
            DocsCatalog::class,
            static fn (): DocsCatalog => new DocsCatalog(base_path('docs')),
        );
        $this->app()->singleton(
            MarkdownRenderer::class,
            static fn (): MarkdownRenderer => new MarkdownRenderer(),
        );
    }

    public function boot(): void
    {
    }
}
