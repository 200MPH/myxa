<?php

declare(strict_types=1);

use App\Http\Controllers\DocsController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\VueTestController;
use Myxa\Support\Facades\Route;

Route::get('/', [HomeController::class, '__invoke']);
Route::get('/vue-test', [VueTestController::class, '__invoke']);
Route::get('/docs', [DocsController::class, 'index']);
Route::get('/docs/{page}', [DocsController::class, 'show']);
Route::get('/health', [HealthController::class, '__invoke']);
