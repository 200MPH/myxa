<?php

declare(strict_types=1);

namespace App\Providers;

use Myxa\Events\EventHandlerInterface;
use Myxa\Events\EventServiceProvider as FrameworkEventServiceProvider;
use Myxa\Support\ServiceProvider;

final class EventServiceProvider extends ServiceProvider
{
    /**
     * Register the application's event bus and listener registry.
     */
    public function register(): void
    {
        $this->app()->register(new FrameworkEventServiceProvider($this->listeners()));
    }

    /**
     * Return the event-to-listener map for the application.
     *
     * @return array<class-string, list<EventHandlerInterface|class-string<EventHandlerInterface>>>
     */
    protected function listeners(): array
    {
        return [];
    }
}
