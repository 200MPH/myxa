<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Config\ConfigRepository;
use App\Docs\DocsCatalog;
use App\Docs\MarkdownRenderer;
use App\Version\ApplicationVersion;
use InvalidArgumentException;
use Myxa\Http\Request;
use Myxa\Http\Response;
use Myxa\Support\Html\Html;

final class DocsController
{
    public function index(
        Request $request,
        ConfigRepository $config,
        ApplicationVersion $version,
        Html $html,
        DocsCatalog $docs,
        MarkdownRenderer $markdown,
    ): Response {
        $slug = $docs->defaultSlug();

        if ($slug === null) {
            return $this->notFound($html, 'No documentation pages are available right now.');
        }

        return $this->renderDoc($slug, $request, $config, $version, $html, $docs, $markdown);
    }

    public function show(
        string $page,
        Request $request,
        ConfigRepository $config,
        ApplicationVersion $version,
        Html $html,
        DocsCatalog $docs,
        MarkdownRenderer $markdown,
    ): Response {
        return $this->renderDoc($page, $request, $config, $version, $html, $docs, $markdown);
    }

    private function renderDoc(
        string $page,
        Request $request,
        ConfigRepository $config,
        ApplicationVersion $version,
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

        $pageTitle = $document['title'];
        $metaDescription = $this->descriptionFromMarkdown($document['markdown']);
        $pageUrl = $request->url();
        $appName = (string) $config->get('app.name', 'Myxa App');
        $appVersion = $version->current();
        $content = $html->renderPage(
            'pages/docs',
            [
                'docs' => $docs->all(),
                'activeSlug' => $document['slug'],
                'content' => $markdown->render($document['markdown']),
                'pageTitle' => $pageTitle,
                'appVersion' => $appVersion,
            ],
            'layouts/app',
            [
                'title' => sprintf('Docs | %s', $pageTitle),
                'faviconPath' => '/assets/images/myxa-mark.svg',
                'metaDescription' => $metaDescription,
                'metaImage' => $this->absoluteUrl(
                    $request,
                    sprintf('/assets/images/myxa-docs-social.png?v=%s', rawurlencode($appVersion)),
                ),
                'metaImageAlt' => sprintf('%s documentation social preview', $appName),
                'metaImageWidth' => '1536',
                'metaImageHeight' => '803',
                'metaSiteName' => $appName,
                'metaType' => 'article',
                'metaUrl' => $pageUrl,
                'twitterCard' => 'summary_large_image',
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

    private function absoluteUrl(Request $request, string $path): string
    {
        $authority = $request->host();
        $port = $request->port();
        $defaultPort = $request->secure() ? 443 : 80;

        if ($port !== null && $port !== $defaultPort) {
            $authority .= ':' . $port;
        }

        return sprintf('%s://%s/%s', $request->scheme(), $authority, ltrim($path, '/'));
    }

    private function descriptionFromMarkdown(string $markdown): string
    {
        $blocks = preg_split('/\R\s*\R/', trim($markdown)) ?: [];

        foreach ($blocks as $block) {
            $candidate = trim($block);

            if ($candidate === '' || preg_match('/^(#{1,6}\s+|```|>\s?|[-*]\s+|\d+\.\s+)/', $candidate) === 1) {
                continue;
            }

            $candidate = preg_replace('/\[(.*?)\]\((.*?)\)/', '$1', $candidate) ?? $candidate;
            $candidate = str_replace(['`', '**', '__', '*', '_'], '', $candidate);
            $candidate = preg_replace('/\s+/', ' ', $candidate) ?? $candidate;
            $candidate = trim($candidate);

            if ($candidate !== '') {
                return $this->truncate($candidate, 200);
            }
        }

        return 'Practical guides for building with Myxa, from first boot to queues, '
            . 'storage, auth, and hybrid frontend work.';
    }

    private function truncate(string $value, int $maxLength): string
    {
        if (strlen($value) <= $maxLength) {
            return $value;
        }

        $truncated = substr($value, 0, $maxLength - 1);
        $lastSpace = strrpos($truncated, ' ');

        if ($lastSpace !== false && $lastSpace >= (int) floor($maxLength * 0.6)) {
            $truncated = substr($truncated, 0, $lastSpace);
        }

        return rtrim($truncated, " \t\n\r\0\x0B.,;:!?") . '…';
    }
}
