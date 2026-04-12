<?php

declare(strict_types=1);

use App\Http\Controllers\HomeController;
use App\Http\Controllers\HealthController;
use Myxa\Support\Facades\Route;

Route::get('/', [HomeController::class, '__invoke']);
Route::get('/health', [HealthController::class, '__invoke']);
