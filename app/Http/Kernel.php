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
            try {
                $handler = $this->app->make(ExceptionHandlerInterface::class);
            } catch (Throwable $handlerFailure) {
                myxa_emergency_log($exception, $handlerFailure);

                return $this->emergencyResponse($request);
            }

            try {
                $handler->report($exception);
            } catch (Throwable $reportFailure) {
                myxa_emergency_log($exception, $reportFailure);

                try {
                    return $handler->render($reportFailure, $request);
                } catch (Throwable $renderFailure) {
                    myxa_emergency_log($reportFailure, $renderFailure);

                    return $this->emergencyResponse($request);
                }
            }

            try {
                return $handler->render($exception, $request);
            } catch (Throwable $renderFailure) {
                myxa_emergency_log($exception, $renderFailure);

                return $this->emergencyResponse($request);
            }
        }
    }

    private function normalizeResponse(mixed $result, Request $request): Response
    {
        if ($result instanceof Response) {
            return $result;
        }

        $response = new Response();

        if ($result === null) {
            return $response->noContent();
        }

        if ($request->expectsJson()) {
            return $response->json($result);
        }

        if (is_array($result) || is_object($result)) {
            return $response->json($result);
        }

        if (is_string($result)) {
            return $response->html($result);
        }

        return $response->text((string) $result);
    }

    private function emergencyResponse(Request $request): Response
    {
        $response = new Response();

        if ($request->expectsJson()) {
            return $response->json([
                'error' => [
                    'type' => 'server_error',
                    'message' => 'Server Error',
                    'status' => 500,
                ],
            ], 500);
        }

        return $response->text('Server Error', 500);
    }
}
