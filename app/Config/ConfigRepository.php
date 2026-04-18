<?php

declare(strict_types=1);

namespace App\Config;

final class ConfigRepository
{
    /**
     * @param array<string, mixed> $items
     */
    public function __construct(private array $items = [])
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->items;
    }

    public function get(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null || $key === '') {
            return $this->items;
        }

        $segments = explode('.', $key);
        $value = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public function has(string $key): bool
    {
        $sentinel = new \stdClass();

        return $this->get($key, $sentinel) !== $sentinel;
    }

    public function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $cursor = &$this->items;

        foreach ($segments as $segment) {
            if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }

            $cursor = &$cursor[$segment];
        }

        $cursor = $value;
    }
}
