<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv\Handler;

/**
 * Records where the winning value of a key came from — one entry per key, last assignment wins.
 * The vocabulary is closed: `env` (process environment), `file:<realpath>` (that file) or
 * `string` (string input). Values are never stored, only their origin.
 */
class SourceRegistry
{
    /** Origin of a value taken from the process environment. */
    public const SOURCE_ENV = 'env';

    /** Origin of a value parsed from string input. */
    public const SOURCE_STRING = 'string';

    /** Prefix of a file origin; the realpath of the file holding the line follows. */
    public const SOURCE_FILE_PREFIX = 'file:';

    /** @var array<string, string> */
    private array $sources = [];

    public function record(string $key, string $source): void
    {
        $this->sources[$key] = $source;
    }

    /** @return array<string, string> */
    public function all(): array
    {
        return $this->sources;
    }

    public function reset(): void
    {
        $this->sources = [];
    }
}
