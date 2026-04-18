<?php

declare(strict_types=1);

namespace App\Providers;

use App\Config\ConfigRepository;
use App\Support\Facades\Config;
use Myxa\Support\ServiceProvider;

final class ConfigServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Config::setRepository($this->app()->make(ConfigRepository::class));
    }
}
