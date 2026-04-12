<?php

declare(strict_types=1);

use App\Http\Controllers\HomeController;
use Myxa\Http\Request;
use Myxa\Support\Facades\Route;

Route::get('/', [HomeController::class, '__invoke']);

Route::get('/health', static function (Request $request): array {
    return [
        'ok' => true,
        'framework' => 'myxa',
        'method' => $request->method(),
        'path' => $request->path(),
        'timestamp' => date(DATE_ATOM),
    ];
});
