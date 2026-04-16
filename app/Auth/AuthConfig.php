<?php

declare(strict_types=1);

namespace App\Auth;

use App\Config\ConfigRepository;

final class AuthConfig
{
    /**
     * Read auth-related runtime configuration from the app config repository.
     */
    public function __construct(private readonly ConfigRepository $config)
    {
    }

    /**
     * Return the cookie name used by the web session guard.
     */
    public function sessionCookieName(): string
    {
        return (string) $this->config->get('auth.session.cookie', 'myxa_session');
    }

    /**
     * Return the session lifetime in seconds.
     */
    public function sessionLifetime(): int
    {
        return max(1, (int) $this->config->get('auth.session.lifetime', 1209600));
    }

    /**
     * Determine whether issued session cookies should be HTTP-only.
     */
    public function sessionHttpOnly(): bool
    {
        return (bool) $this->config->get('auth.session.http_only', true);
    }

    /**
     * Determine whether issued session cookies should be marked secure.
     */
    public function sessionSecure(): bool
    {
        return (bool) $this->config->get('auth.session.secure', false);
    }

    /**
     * Return the SameSite policy applied to issued session cookies.
     */
    public function sessionSameSite(): ?string
    {
        $sameSite = trim((string) $this->config->get('auth.session.same_site', 'Lax'));

        return $sameSite !== '' ? $sameSite : null;
    }

    /**
     * Return the generated session identifier length in hexadecimal characters.
     */
    public function sessionLength(): int
    {
        return max(32, (int) $this->config->get('auth.session.length', 64));
    }

    /**
     * Return the generated bearer token length in hexadecimal characters.
     */
    public function tokenLength(): int
    {
        return max(32, (int) $this->config->get('auth.tokens.length', 40));
    }

    /**
     * Return the fallback display name used when issuing tokens from the CLI.
     *
     * @return non-empty-string
     */
    public function defaultTokenName(): string
    {
        $name = trim((string) $this->config->get('auth.tokens.default_name', 'cli'));

        return $name !== '' ? $name : 'cli';
    }

    /**
     * Return the default scopes assigned to newly issued tokens.
     *
     * @return list<string>
     */
    public function defaultTokenScopes(): array
    {
        $scopes = $this->config->get('auth.tokens.default_scopes', ['*']);

        if (!is_array($scopes)) {
            return ['*'];
        }

        $normalized = array_values(array_filter(array_map(
            static fn (mixed $scope): string => is_string($scope) ? trim($scope) : '',
            $scopes,
        ), static fn (string $scope): bool => $scope !== ''));

        return $normalized !== [] ? $normalized : ['*'];
    }
}
