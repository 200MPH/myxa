<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Docs\DocsCatalog;
use App\Docs\MarkdownRenderer;
use InvalidArgumentException;
use Myxa\Http\Response;
use Myxa\Support\Html\Html;

final class DocsController
{
    public function index(
        Html $html,
        DocsCatalog $docs,
        MarkdownRenderer $markdown,
    ): Response {
        $slug = $docs->defaultSlug();

        if ($slug === null) {
            return $this->notFound($html, 'No documentation pages are available right now.');
        }

        return $this->renderDoc($slug, $html, $docs, $markdown);
    }

    public function show(
        string $page,
        Html $html,
        DocsCatalog $docs,
        MarkdownRenderer $markdown,
    ): Response {
        return $this->renderDoc($page, $html, $docs, $markdown);
    }

    private function renderDoc(
        string $page,
        Html $html,
        DocsCatalog $docs,
        MarkdownRenderer $markdown,
    ): Response {
        try {
            $document = $docs->find($page);
        } catch (InvalidArgumentException) {
            $document = null;
        }

        if ($document === null) {
            return $this->notFound($html, sprintf('The docs page "%s" was not found.', $page));
        }

        $content = $html->renderPage(
            'pages/docs',
            [
                'docs' => $docs->all(),
                'activeSlug' => $document['slug'],
                'content' => $markdown->render($document['markdown']),
                'pageTitle' => $document['title'],
            ],
            'layouts/app',
            [
                'title' => sprintf('Docs | %s', $document['title']),
                'faviconPath' => '/assets/images/myxa-mark.svg',
            ],
        );

        return (new Response())->html($content);
    }

    private function notFound(Html $html, string $message): Response
    {
        $content = $html->renderPage(
            'errors/show',
            [
                'status' => 404,
                'heading' => 'Page not found',
                'message' => $message,
                'errorType' => 'docs_not_found',
                'homePath' => '/',
                'debug' => false,
                'debugData' => [
                    'exception' => '',
                    'message' => '',
                    'file' => '',
                    'line' => '',
                    'request' => '',
                    'trace' => [],
                ],
            ],
            'layouts/error',
            [
                'title' => 'Docs | 404',
                'faviconPath' => '/assets/images/myxa-mark.svg',
            ],
        );

        return (new Response())->html($content, 404);
    }
}
