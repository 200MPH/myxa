# Events, Listeners, and Services

This project includes both an event system and a standard provider/container setup for registering your own services.

## Events and Listeners

Event classes live under:

```text
app/Events
```

Listener classes live under:

```text
app/Listeners
```

Generate an event:

```bash
./myxa make:event UserRegistered
./myxa make:event Auth/UserLoggedIn
```

Generate a listener:

```bash
./myxa make:listener SendWelcomeEmail
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

## Event Example

Example event:

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

Example listener:

```php
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

Dispatch the event:

```php
use Myxa\Support\Facades\Event;

Event::dispatch(new UserRegistered(1, 'john@example.com'));
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

## How Events Are Processed

The current event bus is:

- synchronous
- container-aware
- class-name based

That means:

- the dispatch call runs listeners immediately
- listener classes are resolved through the container

## Registering Services

For small app-level bindings, `AppServiceProvider` is the easiest place to start.

Example:

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

## When to Create a New Provider

Use `AppServiceProvider` when:

- the bindings are small
- the service belongs to core app bootstrapping

Create a dedicated provider when:

- a feature area has multiple bindings
- the setup has its own configuration
- you want a clearer boundary, such as cache, storage, auth, or rate limiting

Example dedicated provider:

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

Then register it in:

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

Example controller:

```php
final class ReportController
{
    public function __construct(private readonly ReportService $reports)
    {
    }
}
```

Example command:

```php
final class SendReportCommand extends Command
{
    public function __construct(private readonly ReportService $reports)
    {
    }
}
```

## Container Resolution Rules

Concrete classes often do not need manual registration if they can be autowired.

Still, explicit bindings are useful when:

- you want an interface mapped to an implementation
- you need shared singleton state
- you need config-driven construction logic

## Notes

- The project already uses dedicated providers for cache, storage, auth, redis, rate limiting, events, and routes.
- `EventServiceProvider` is the source of truth for event-to-listener mapping.
- `make:listener --event=...` is the fastest way to scaffold both the class and the registration.

## Further Reading

- `vendor/200mph/myxa-framework/src/Events/README.md`
- `vendor/200mph/myxa-framework/src/Container/README.md`
- [Configuration](configuration.md)
