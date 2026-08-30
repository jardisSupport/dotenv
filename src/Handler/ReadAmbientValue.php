<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv\Handler;

/**
 * Reads the value a key already carries in the process environment, i.e. the value that wins over
 * the one parsed from a .env file or string. A key is ambient when getenv() returns a non-empty
 * string and the key is not listed in the JARDIS_DOTENV_VARS marker — that marker names the keys
 * this library published itself, which are file values and therefore never treated as ambient.
 */
class ReadAmbientValue
{
    /** Environment variable holding the comma-separated list of keys published by this library. */
    public const MARKER_KEY = 'JARDIS_DOTENV_VARS';

    public function __invoke(string $key): ?string
    {
        if ($key === self::MARKER_KEY || $this->isPublishedByLibrary($key)) {
            return null;
        }

        $value = getenv($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function isPublishedByLibrary(string $key): bool
    {
        $marker = getenv(self::MARKER_KEY);

        if (!is_string($marker) || $marker === '') {
            return false;
        }

        return in_array($key, explode(',', $marker), true);
    }
}
