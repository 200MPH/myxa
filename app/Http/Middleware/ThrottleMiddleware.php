<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Config\ConfigRepository;
use Closure;
use InvalidArgumentException;
use Myxa\Http\Request;
use Myxa\Middleware\MiddlewareInterface;
use Myxa\Middleware\RateLimitMiddleware;
use Myxa\RateLimit\RateLimiter;
use Myxa\Routing\RouteDefinition;

final class ThrottleMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly RateLimiter $limiter,
        private readonly int $maxAttempts = 60,
        private readonly int $decaySeconds = 60,
        private readonly ?string $prefix = null,
    ) {
    }

    public function handle(Request $request, Closure $next, RouteDefinition $route): mixed
    {
        return (new RateLimitMiddleware(
            $this->limiter,
            $this->maxAttempts,
            $this->decaySeconds,
            $this->prefix,
        ))->handle($request, $next, $route);
    }

    /**
     * Build a route middleware callable using a named preset from rate_limit.php.
     */
    public static function using(string $preset): Closure
    {
        return static function (
            Request $request,
            Closure $next,
            RouteDefinition $route,
            RateLimiter $limiter,
            ConfigRepository $config,
        ) use (
            $preset,
        ): mixed {
            $resolved = self::resolvePreset($config, $preset);

            return (new self(
                $limiter,
                $resolved['max_attempts'],
                $resolved['decay_seconds'],
                $resolved['prefix'],
            ))->handle($request, $next, $route);
        };
    }

    /**
     * Resolve a named rate limit preset into concrete middleware settings.
     *
     * @return array{max_attempts: int, decay_seconds: int, prefix: string|null}
     */
    public static function resolvePreset(ConfigRepository $config, string $preset): array
    {
        $resolvedPreset = trim($preset);
        if ($resolvedPreset === '') {
            throw new InvalidArgumentException('Rate limit preset name cannot be empty.');
        }

        $settings = $config->get(sprintf('rate_limit.presets.%s', $resolvedPreset), []);

        if (!is_array($settings)) {
            throw new InvalidArgumentException(sprintf('Rate limit preset [%s] is not configured.', $resolvedPreset));
        }

        $maxAttempts = is_numeric($settings['max_attempts'] ?? null) ? (int) $settings['max_attempts'] : 60;
        $decaySeconds = is_numeric($settings['decay_seconds'] ?? null) ? (int) $settings['decay_seconds'] : 60;
        $prefix = $settings['prefix'] ?? $resolvedPreset;

        if ($maxAttempts < 1 || $decaySeconds < 1) {
            throw new InvalidArgumentException(sprintf('Rate limit preset [%s] contains invalid limits.', $resolvedPreset));
        }

        return [
            'max_attempts' => $maxAttempts,
            'decay_seconds' => $decaySeconds,
            'prefix' => is_string($prefix) && trim($prefix) !== '' ? trim($prefix) : null,
        ];
    }
}
