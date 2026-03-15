<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv\Handler;

/**
 * Central registry for resolved environment variable values.
 * Used by CastStringToValue for ${VAR} substitution in both public and private mode.
 */
class VariableRegistry
{
    /** @var array<string, string> */
    private array $variables = [];

    public function set(string $key, string $value): void
    {
        $this->variables[$key] = $value;
    }

    public function get(string $key): ?string
    {
        if (isset($this->variables[$key])) {
            return $this->variables[$key];
        }

        $envValue = getenv($key);

        return is_string($envValue) && $envValue !== '' ? $envValue : null;
    }

    public function reset(): void
    {
        $this->variables = [];
    }
}
