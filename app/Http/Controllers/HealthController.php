<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Myxa\Http\Request;

final class HealthController
{
    public function __invoke(Request $request): array
    {
        return [
            'ok' => true,
            'framework' => 'myxa',
            'method' => $request->method(),
            'path' => $request->path(),
            'timestamp' => date(DATE_ATOM),
        ];
    }
}
