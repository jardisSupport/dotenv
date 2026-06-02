<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv\Handler;

use RuntimeException;

/**
 * Return home path of active user
 */
class CastUserHome
{
    public const HOME_DRIVE = 'HOMEDRIVE';
    public const HOME_PATH = 'HOMEPATH';
    public const HOME = 'HOME';

    private VariableRegistry $registry;

    public function __construct(VariableRegistry $registry)
    {
        $this->registry = $registry;
    }

    /** @throws RuntimeException */
    public function __invoke(?string $value = null): ?string
    {
        if (is_string($value) && str_contains($value, '~')) {
            if (strpos(trim($value), '~') === 0) {
                $value = trim($value);
                $homeDir = $this->getHomeDir();

                if (empty($homeDir)) {
                    throw new RuntimeException('HOME environment variable is not set!');
                }

                return $homeDir . substr($value, 1);
            }
        }

        return $value;
    }

    protected function getHomeDir(): ?string
    {
        if ($this->getOsType() === 'Windows') {
            $homeDrive = $this->resolveEnvVar(static::HOME_DRIVE);
            $homePath = $this->resolveEnvVar(static::HOME_PATH);

            if (is_string($homeDrive) && is_string($homePath)) {
                $result = $homeDrive . $homePath;
            } else {
                $result = false;
            }
        } else {
            $result = $this->resolveEnvVar(static::HOME);
        }

        return is_string($result) && $result !== '' ? $result : null;
    }

    /**
     * Resolve an environment variable: registry first (skip ~ values to avoid circular),
     * then getenv() as fallback.
     */
    private function resolveEnvVar(string $key): string|false
    {
        $registryValue = $this->registry->get($key);

        if ($registryValue !== null && !str_contains($registryValue, '~')) {
            return $registryValue;
        }

        // Fallback to OS environment directly
        return getenv($key);
    }

    protected function getOsType(): string
    {
        return PHP_OS_FAMILY;
    }
}
