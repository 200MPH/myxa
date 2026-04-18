<?php

declare(strict_types=1);

namespace App\Maintenance;

use Myxa\Http\Request;
use Myxa\Http\Response;
use Myxa\Support\Html\Html;
use Throwable;

final class MaintenanceResponseFactory
{
    private readonly string $basePath;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = rtrim($basePath ?? base_path(), DIRECTORY_SEPARATOR);
    }

    /**
     * @param array{
     *     enabled_at?: string,
     *     enabled_at_unix?: int,
     *     activated_by?: string,
     *     activated_by_pid?: int
     * } $payload
     */
    public function forRequest(Request $request, array $payload = []): Response
    {
        $response = $request->expectsJson()
            ? $this->jsonResponse()
            : $this->htmlResponse($payload);

        return $response->setHeader('Retry-After', '60');
    }

    /**
     * @param array{
     *     enabled_at?: string,
     *     enabled_at_unix?: int,
     *     activated_by?: string,
     *     activated_by_pid?: int
     * } $payload
     */
    public function emitFromGlobals(array $payload = []): void
    {
        $this->forRequest(Request::capture(), $payload)->send();
    }

    private function jsonResponse(): Response
    {
        return (new Response())->json([
            'error' => [
                'type' => 'maintenance_mode',
                'message' => 'Service Unavailable',
                'status' => 503,
            ],
        ], 503);
    }

    /**
     * @param array{
     *     enabled_at?: string,
     *     enabled_at_unix?: int,
     *     activated_by?: string,
     *     activated_by_pid?: int
     * } $payload
     */
    private function htmlResponse(array $payload): Response
    {
        try {
            $html = new Html($this->basePath . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views');
            $content = $html->renderPage(
                'pages/maintenance',
                [
                    'enabledAt' => $this->enabledAtLabel($payload),
                ],
                'layouts/error',
                [
                    'title' => sprintf('%s | Maintenance', (string) env('APP_NAME', 'Myxa App')),
                    'faviconPath' => '/assets/images/myxa-mark.svg',
                ],
            );

            return (new Response())->html($content, 503);
        } catch (Throwable) {
            return (new Response())->text('Service Unavailable', 503);
        }
    }

    /**
     * @param array{
     *     enabled_at?: string,
     *     enabled_at_unix?: int,
     *     activated_by?: string,
     *     activated_by_pid?: int
     * } $payload
     */
    private function enabledAtLabel(array $payload): ?string
    {
        $value = $payload['enabled_at'] ?? null;
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return (new \DateTimeImmutable($value))->format('Y-m-d H:i:s T');
        } catch (Throwable) {
            return $value;
        }
    }
}
