<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv\Reader;

use InvalidArgumentException;
use JardisSupport\DotEnv\Handler\CastTypeHandler;
use JardisSupport\DotEnv\Handler\MatchesRawKey;
use JardisSupport\DotEnv\Handler\ReadAmbientValue;
use JardisSupport\DotEnv\Handler\SourceRegistry;
use JardisSupport\DotEnv\Exception\EnvFileNotFoundException;
use JardisSupport\DotEnv\Exception\EnvFileNotReadableException;
use JardisSupport\DotEnv\Exception\IncludeNotSupportedException;

/**
 * Parses KEY=VALUE rows into a cast value array. This is the shared engine behind file-based and
 * string-based loading: it owns comment/blank skipping, load() directive detection, _FILE secret
 * resolution, raw-key cast exemption and publishing — everything that does not require file-system
 * recursion. Include resolution itself is delegated to an injected handler; without one, a load()
 * directive is a hard error (the string-loading caller has no file-system context to resolve it).
 *
 * Precedence: a key already set in the process environment beats the parsed value. The ambient
 * value then runs through the same cast chain, raw-key exemption and registry write as a file
 * value. Keys published by this library carry the JARDIS_DOTENV_VARS marker and never count as
 * ambient, so the .env -> .env.local -> .env.{APP_ENV} cascade keeps overriding itself.
 *
 * Source tracking: every assignment records the origin of the winning value in the SourceRegistry —
 * `env` when the process environment won, otherwise the origin the caller passed for these rows
 * (`file:<realpath>` or `string`). Rows invoked without an origin record nothing unless the process
 * environment won, because there is no origin to name.
 */
class LoadValuesFromRows
{
    private CastTypeHandler $castTypeHandler;
    private ParseLoadDirective $parseLoadDirective;
    private MatchesRawKey $matchesRawKey;
    private ReadAmbientValue $readAmbientValue;
    private SourceRegistry $sourceRegistry;

    /** @var (callable(array{path: string, optional: bool}, bool, ?string): array<string, mixed>)|null */
    private $includeHandler;

    /**
     * @param (callable(array{path: string, optional: bool}, bool, ?string): array<string, mixed>)|null $includeHandler
     */
    public function __construct(
        CastTypeHandler $castTypeHandler,
        ?ParseLoadDirective $parseLoadDirective = null,
        ?MatchesRawKey $matchesRawKey = null,
        ?callable $includeHandler = null,
        ?ReadAmbientValue $readAmbientValue = null,
        ?SourceRegistry $sourceRegistry = null
    ) {
        $this->castTypeHandler = $castTypeHandler;
        $this->parseLoadDirective = $parseLoadDirective ?? new ParseLoadDirective();
        $this->matchesRawKey = $matchesRawKey ?? new MatchesRawKey();
        $this->includeHandler = $includeHandler;
        $this->readAmbientValue = $readAmbientValue ?? new ReadAmbientValue();
        $this->sourceRegistry = $sourceRegistry ?? new SourceRegistry();
    }

    /**
     * @param array<string> $rows
     * @param string|null $origin Origin recorded for values these rows win with — `file:<realpath>`
     *                            or `string`; null records no file/string origin.
     * @return array<string, mixed>
     * @throws EnvFileNotFoundException
     * @throws EnvFileNotReadableException
     * @throws IncludeNotSupportedException
     */
    public function __invoke(array $rows, bool $public, ?string $baseDir, ?string $origin = null): array
    {
        $result = [];

        foreach ($rows as $row) {
            $trimmedRow = trim($row);

            // Skip comments
            if (strpos($trimmedRow, '#') === 0) {
                continue;
            }

            // Check for load directive
            $loadDirective = ($this->parseLoadDirective)($trimmedRow);

            if ($loadDirective !== null) {
                $result = array_merge($result, $this->handleInclude($loadDirective, $public, $baseDir));
                continue;
            }

            // Skip lines without '=' (not a valid KEY=VALUE line)
            if (strpos($row, '=') === false) {
                continue;
            }

            list($key, $value) = explode('=', $row, 2);
            $key = trim($key);
            $value = $value !== '' ? trim($value) : $value;

            // Resolve _FILE suffix: read file content as value
            if (str_ends_with($key, '_FILE') && strlen($key) > 5) {
                $fileResult = $this->resolveFileValue($key, $value, $public, $baseDir, $origin);
                $result = array_merge($result, $fileResult);
                continue;
            }

            $result = array_merge($result, $this->assignValue($key, $value, $public, $origin));
        }

        return $result;
    }

    /**
     * @param array{path: string, optional: bool} $directive
     * @return array<string, mixed>
     * @throws IncludeNotSupportedException
     */
    private function handleInclude(array $directive, bool $public, ?string $baseDir): array
    {
        if ($this->includeHandler === null) {
            throw new IncludeNotSupportedException($directive['path']);
        }

        return ($this->includeHandler)($directive, $public, $baseDir);
    }

    /**
     * @return array<string, mixed>
     * @throws EnvFileNotFoundException
     * @throws EnvFileNotReadableException
     */
    private function resolveFileValue(
        string $fileKey,
        string $filePath,
        bool $public,
        ?string $baseDir,
        ?string $origin = null
    ): array {
        $resolvedKey = substr($fileKey, 0, -5);

        // A key already set in the process environment wins; the secret file is not read at all.
        $ambientValue = ($this->readAmbientValue)($resolvedKey);

        if ($ambientValue !== null) {
            return $this->assignValue($resolvedKey, $ambientValue, $public, $origin);
        }

        // Resolve relative paths from the including file's directory
        if ($filePath !== '' && $filePath[0] !== '/') {
            if ($baseDir === null) {
                $message = 'Relative "' . $fileKey . '" path requires a base directory: ' . $filePath;
                throw new InvalidArgumentException($message);
            }
            $filePath = $baseDir . '/' . $filePath;
        }

        if (!file_exists($filePath)) {
            throw new EnvFileNotFoundException($filePath);
        }

        if (!is_readable($filePath)) {
            throw new EnvFileNotReadableException($filePath);
        }

        $value = trim(file_get_contents($filePath) ?: '');

        return $this->assignValue($resolvedKey, $value, $public, $origin);
    }

    /**
     * Assigns the winning value for a key and records its origin.
     *
     * @return array<string, mixed>
     */
    private function assignValue(string $key, string $value, bool $public, ?string $origin = null): array
    {
        $ambientValue = ($this->readAmbientValue)($key);
        $value = $ambientValue ?? $value;

        $source = $ambientValue !== null ? SourceRegistry::SOURCE_ENV : $origin;
        if ($source !== null) {
            $this->sourceRegistry->record($key, $source);
        }

        $this->castTypeHandler->getRegistry()->set($key, $value);

        // Raw keys skip the casts but still pass through registered value handlers.
        $typeCastValue = ($this->matchesRawKey)($key)
            ? $this->castTypeHandler->resolveRawValue($value)
            : ($this->castTypeHandler)($value);

        if ($public) {
            $this->publish($key, $value, $typeCastValue);
            return [];
        }

        return [$key => $typeCastValue];
    }

    /**
     * Publishes a key/value pair to the OS environment and PHP superglobals.
     *
     * By design, putenv() always receives the raw string value because the OS environment
     * only supports strings. getenv() will therefore always return a string (e.g. "true", "123").
     * $_ENV and $_SERVER receive the type-cast value (e.g. bool(true), int(123)) — except for
     * arrays, which are stored as their raw string representation since arrays cannot be serialised
     * into an environment variable.
     *
     * Every published key is additionally recorded in the JARDIS_DOTENV_VARS marker so that a
     * later load does not mistake this library's own putenv() for a process-environment value.
     *
     * @param string $key       The environment variable name.
     * @param string $value     The raw string value (as read from the .env file/string).
     * @param mixed  $castValue The type-cast value after the full handler chain.
     */
    private function publish(string $key, string $value, mixed $castValue): void
    {
        $publishValue = is_array($castValue) ? $value : $castValue;
        putenv("$key=$value");
        $_ENV[$key] = $publishValue;
        $_SERVER[$key] = $publishValue;
        $this->markPublished($key);
    }

    /**
     * Appends a key to the JARDIS_DOTENV_VARS marker (comma-separated, duplicate-free).
     */
    private function markPublished(string $key): void
    {
        $marker = getenv(ReadAmbientValue::MARKER_KEY);
        $keys = is_string($marker) && $marker !== '' ? explode(',', $marker) : [];

        if (in_array($key, $keys, true)) {
            return;
        }

        $keys[] = $key;
        $markerValue = implode(',', $keys);
        putenv(ReadAmbientValue::MARKER_KEY . '=' . $markerValue);
        $_ENV[ReadAmbientValue::MARKER_KEY] = $markerValue;
        $_SERVER[ReadAmbientValue::MARKER_KEY] = $markerValue;
    }
}
