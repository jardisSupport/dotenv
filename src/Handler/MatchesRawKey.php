<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv\Handler;

/**
 * Tracks a case-insensitive set of keys/suffixes whose values skip the casts and survive as
 * raw strings (registered value handlers still run for them). Suffix and exact match share one
 * check: an exact key is a suffix equal to the whole string.
 */
class MatchesRawKey
{
    /** @var array<string> Upper-cased keys/suffixes */
    private array $rawKeys = [];

    public function __invoke(string $key): bool
    {
        $upperKey = strtoupper($key);

        foreach ($this->rawKeys as $rawKey) {
            if (str_ends_with($upperKey, $rawKey)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string> $keysOrSuffixes
     */
    public function addRawKeys(array $keysOrSuffixes): void
    {
        foreach ($keysOrSuffixes as $keyOrSuffix) {
            $upper = strtoupper($keyOrSuffix);

            if (!in_array($upper, $this->rawKeys, true)) {
                $this->rawKeys[] = $upper;
            }
        }
    }
}
