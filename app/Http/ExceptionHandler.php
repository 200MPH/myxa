<?php

declare(strict_types=1);

namespace App\Http;

use App\Config\ConfigRepository;
use Myxa\Auth\Exceptions\AuthenticationException;
use Myxa\Http\ExceptionHttpMapper;
use Myxa\Http\ExceptionHandlerInterface;
use Myxa\Http\Request;
use Myxa\Http\Response;
use Myxa\Logging\LogLevel;
use Myxa\Logging\LoggerInterface;
use Myxa\Logging\NullLogger;
use Myxa\RateLimit\Exceptions\TooManyRequestsException;
use Myxa\Routing\Exceptions\MethodNotAllowedException;
use Myxa\Support\Html\Html;
use Throwable;

final class ExceptionHandler implements ExceptionHandlerInterface
{
    private readonly bool $debug;

    private readonly string $appName;

    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly Html $html,
        ConfigRepository $config,
        ?LoggerInterface $logger = null,
    ) {
        $this->debug = (bool) $config->get('app.debug', false);
        $this->appName = (string) $config->get('app.name', 'Myxa App');
        $this->logger = $logger ?? new NullLogger();
    }

    public function report(Throwable $exception): void
    {
        $status = ExceptionHttpMapper::statusCodeFor($exception);

        $this->logger->log(
            $status >= 500 ? LogLevel::Error : LogLevel::Warning,
            $exception->getMessage(),
            [
                'exception' => $exception,
                'status' => $status,
                'type' => $this->errorTypeFor($exception),
            ],
        );
    }

    public function render(Throwable $exception, Request $request): Response
    {
        $status = ExceptionHttpMapper::statusCodeFor($exception);

        if ($exception instanceof AuthenticationException && !$request->expectsJson()) {
            return (new Response())->redirect($exception->redirectTo());
        }

        $response = $request->expectsJson()
            ? $this->renderJson($exception, $request, $status)
            : $this->renderHtml($exception, $request, $status);

        return $this->decorateResponse($response, $exception);
    }

    protected function messageFor(Throwable $exception, int $status): string
    {
        if ($this->debug) {
            $message = trim($exception->getMessage());

            return $message !== '' ? $message : sprintf('%s thrown.', $exception::class);
        }

        if ($status >= 500) {
            return 'Server Error';
        }

        return $exception->getMessage();
    }

    private function renderJson(Throwable $exception, Request $request, int $status): Response
    {
        $payload = [
            'error' => [
                'type' => $this->errorTypeFor($exception),
                'message' => $this->messageFor($exception, $status),
                'status' => $status,
            ],
        ];

        if ($this->debug) {
            $payload['error']['debug'] = $this->debugData($exception, $request);
        }

        return (new Response())->json($payload, $status);
    }

    private function renderHtml(Throwable $exception, Request $request, int $status): Response
    {
        try {
            $content = $this->html->renderPage(
                'errors/show',
                [
                    'status' => $status,
                    'heading' => $this->headingFor($status),
                    'message' => $this->messageFor($exception, $status),
                    'errorType' => $this->errorTypeFor($exception),
                    'homePath' => '/',
                    'debug' => $this->debug,
                    'debugData' => $this->debugData($exception, $request),
                ],
                'layouts/error',
                [
                    'title' => sprintf('%s | %d', $this->appName, $status),
                    'faviconPath' => '/assets/images/myxa-mark.svg',
                ],
            );

            return (new Response())->html($content, $status);
        } catch (Throwable) {
            return (new Response())->text($this->messageFor($exception, $status), $status);
        }
    }

    private function decorateResponse(Response $response, Throwable $exception): Response
    {
        if ($exception instanceof MethodNotAllowedException) {
            $response->setHeader('Allow', implode(', ', $exception->allowedMethods()));
        }

        if ($exception instanceof AuthenticationException && $exception->guard() === 'api') {
            $response->setHeader('WWW-Authenticate', 'Bearer');
        }

        if ($exception instanceof TooManyRequestsException) {
            $result = $exception->result();
            $response->setHeader('Retry-After', (string) $result->retryAfter);
            $response->setHeader('X-RateLimit-Limit', (string) $result->maxAttempts);
            $response->setHeader('X-RateLimit-Remaining', (string) $result->remaining);
            $response->setHeader('X-RateLimit-Reset', (string) $result->resetsAt);
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function debugData(Throwable $exception, Request $request): array
    {
        return [
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'request' => sprintf('%s %s', $request->method(), $request->path()),
            'trace' => array_slice(explode("\n", $exception->getTraceAsString()), 0, 10),
        ];
    }

    private function headingFor(int $status): string
    {
        return match ($status) {
            400 => 'Bad request',
            401 => 'Authentication required',
            404 => 'Page not found',
            405 => 'Method not allowed',
            429 => 'Too many requests',
            default => $status >= 500 ? 'Something went wrong' : 'Request failed',
        };
    }

    private function errorTypeFor(Throwable $exception): string
    {
        if ($exception instanceof AuthenticationException) {
            return 'unauthenticated';
        }

        $class = $exception::class;
        $position = strrpos($class, '\\');
        $base = $position === false ? $class : substr($class, $position + 1);
        $base = str_ends_with($base, 'Exception') ? substr($base, 0, -9) : $base;

        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $base));
    }
}
