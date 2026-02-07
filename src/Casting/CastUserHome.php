<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv\Casting;

use RuntimeException;

/**
 * Return home path of active user
 */
class CastUserHome
{
    public const HOME_DRIVE = 'HOMEDRIVE';
    public const HOME_PATH = 'HOMEPATH';
    public const HOME = 'HOME';

    /** @throws RuntimeException */
    public function __invoke(?string $value = null): ?string
    {
        if (is_string($value) && str_contains($value, '~')) {
            $value = trim($value);

            if (strpos($value, '~') === 0) {
                $homeDir = $this->getHomeDir();

                if (empty($homeDir)) {
                    throw new RuntimeException('HOME environment variable is not set!');
                }

                return str_replace('~', $homeDir, $value);
            }
        }

        return $value;
    }

    protected function getHomeDir(): ?string
    {
        if ($this->getOsType() === 'Windows') {
            $homeDrive = getenv(static::HOME_DRIVE);
            $homePath = getenv(static::HOME_PATH);

            if (is_string($homeDrive) && is_string($homePath)) {
                $result = $homeDrive . $homePath;
            } else {
                $result = false;
            }
        } else {
            $result = getenv(static::HOME);
        }

        return is_string($result) && $result !== '' ? $result : null;
    }

    protected function getOsType(): string
    {
        return PHP_OS_FAMILY;
    }
}
