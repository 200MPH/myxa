<?php

declare(strict_types=1);

namespace App\Http;

use Myxa\Application;
use Myxa\Http\ExceptionHandlerInterface;
use Myxa\Http\Request;
use Myxa\Http\Response;
use Myxa\Routing\Router;
use Throwable;

final class Kernel
{
    public function __construct(private readonly Application $app)
    {
    }

    public function handle(?Request $request = null): Response
    {
        $request ??= $this->app->make(Request::class);

        try {
            $result = $this->app->make(Router::class)->dispatch($request);

            return $this->normalizeResponse($result, $request);
        } catch (Throwable $exception) {
            $handler = $this->app->make(ExceptionHandlerInterface::class);
            $handler->report($exception);

            return $handler->render($exception, $request);
        }
    }

    private function normalizeResponse(mixed $result, Request $request): Response
    {
        if ($result instanceof Response) {
            return $result;
        }

        $response = new Response();

        if ($request->expectsJson()) {
            return $response->json($result);
        }

        if ($result === null) {
            return $response->noContent();
        }

        if (is_array($result) || is_object($result)) {
            return $response->json($result);
        }

        if (is_string($result)) {
            return $response->html($result);
        }

        return $response->text((string) $result);
    }
}
