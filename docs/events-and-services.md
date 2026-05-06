# Events, Listeners, and Services

Use events when one part of the app needs to announce that something happened.
Use services and providers when you want reusable app behavior registered in the container.

These belong together because listeners, controllers, commands, and other services can all receive container-injected dependencies.

## On This Page

- [Quick Example](#quick-example)
- [Events and Listeners](#events-and-listeners)
- [Event Classes](#event-classes)
- [Listener Classes](#listener-classes)
- [Manual Listener Registration](#manual-listener-registration)
- [How Events Are Processed](#how-events-are-processed)
- [Services and Providers](#services-and-providers)
- [Dedicated Providers](#dedicated-providers)
- [Constructor Injection](#constructor-injection)
- [Notes](#notes)
- [Related Guides](#related-guides)

## Quick Example

Generate an event:

```bash
./myxa make:event UserRegistered
```

Generate a listener and register it for that event:

```bash
./myxa make:listener SendWelcomeEmail --event=UserRegistered
```

Dispatch the event:

```php
use App\Events\UserRegistered;
use Myxa\Support\Facades\Event;

Event::dispatch(new UserRegistered(1, 'john@example.com'));
```

The current event bus is synchronous, so listeners run during the `dispatch()` call.

## Events and Listeners

Event classes live in:

```text
app/Events
```

Listener classes live in:

```text
app/Listeners
```

Generate events:

```bash
./myxa make:event UserRegistered
./myxa make:event Auth/UserLoggedIn
```

Generate listeners:

```bash
./myxa make:listener SendWelcomeEmail
./myxa make:listener Auth/TrackLogin
```

Generate and auto-register a listener for an event:

```bash
./myxa make:listener SendWelcomeEmail --event=UserRegistered
./myxa make:listener Auth/TrackLogin --event=Auth/UserLoggedIn
```

When you pass `--event`, the generator updates:

```text
app/Providers/EventServiceProvider.php
```

## Event Classes

Event classes usually extend `AbstractEvent`:

```php
use Myxa\Events\AbstractEvent;

final readonly class UserRegistered extends AbstractEvent
{
    public function __construct(
        public int $userId,
        public string $email,
    ) {
        parent::__construct();
    }
}
```

## Listener Classes

Listeners implement `EventHandlerInterface`:

```php
use App\Events\UserRegistered;
use Myxa\Events\EventHandlerInterface;
use Myxa\Events\EventInterface;

final class SendWelcomeEmailListener implements EventHandlerInterface
{
    public function handle(EventInterface $event): void
    {
        if (!$event instanceof UserRegistered) {
            return;
        }

        // Send email, record analytics, dispatch another event, and so on.
    }
}
```

Listeners are resolved through the container, so they can receive services in their constructor:

```php
use App\Events\UserRegistered;
use App\Services\Mailer;
use Myxa\Events\EventHandlerInterface;
use Myxa\Events\EventInterface;

final class SendWelcomeEmailListener implements EventHandlerInterface
{
    public function __construct(private readonly Mailer $mailer)
    {
    }

    public function handle(EventInterface $event): void
    {
        if (!$event instanceof UserRegistered) {
            return;
        }

        $this->mailer->welcome($event->email);
    }
}
```

## Manual Listener Registration

If you create listeners manually or do not use `--event`, register them in:

```php
// app/Providers/EventServiceProvider.php
protected function listeners(): array
{
    return [
        \App\Events\UserRegistered::class => [
            \App\Listeners\SendWelcomeEmailListener::class,
        ],
    ];
}
```

`EventServiceProvider` is the source of truth for event-to-listener mapping.

## How Events Are Processed

The event bus is:

- synchronous
- container-aware
- class-name based

That means:

- `Event::dispatch(...)` runs listeners immediately
- listener classes are resolved through the container
- listeners are mapped by event class name

If a listener does slow work, dispatch a queued job from the listener instead of doing all of the work inline.

## Services and Providers

For small app-level bindings, `AppServiceProvider` is the easiest place to start.

```php
use Myxa\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app()->singleton(ReportService::class);
        $this->app()->singleton(
            'report.service',
            static fn ($app) => $app->make(ReportService::class),
        );
    }
}
```

The container supports:

- `bind()` for transient services
- `singleton()` for shared services
- `instance()` for already-built values

## Dedicated Providers

Use `AppServiceProvider` when:

- the bindings are small
- the service belongs to core app bootstrapping

Create a dedicated provider when:

- a feature area has multiple bindings
- the setup has its own configuration
- you want a clearer boundary, such as cache, storage, auth, or rate limiting

Example provider:

```php
use Myxa\Support\ServiceProvider;

final class ReportsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app()->singleton(ReportFormatter::class);
        $this->app()->singleton(ReportExporter::class);
    }
}
```

Register it in:

```php
// config/app.php
'providers' => [
    // ...
    App\Providers\ReportsServiceProvider::class,
],
```

## Constructor Injection

Once a service is registered, you can inject it into:

- controllers
- commands
- listeners
- other services

Controller example:

```php
final class ReportController
{
    public function __construct(private readonly ReportService $reports)
    {
    }
}
```

Command example:

```php
final class SendReportCommand extends Command
{
    public function __construct(private readonly ReportService $reports)
    {
    }
}
```

Concrete classes often do not need manual registration if they can be autowired.

Explicit bindings are useful when:

- you want an interface mapped to an implementation
- you need shared singleton state
- you need config-driven construction logic

## Notes

- The project already uses dedicated providers for cache, storage, auth, redis, rate limiting, events, routes, queues, and database access.
- Use `make:listener --event=...` when you want to scaffold both the listener class and registration.
- Keep event listeners quick. Use queues for slow, retryable side effects.

## Related Guides

- [Configuration](configuration.md)
- [Console and Scaffolding](console-and-scaffolding.md)
- [Queues](queues.md)
- `vendor/200mph/myxa-framework/src/Events/README.md`
- `vendor/200mph/myxa-framework/src/Container/README.md`
