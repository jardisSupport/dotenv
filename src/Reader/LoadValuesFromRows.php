<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv\Reader;

use InvalidArgumentException;
use JardisSupport\DotEnv\Handler\CastTypeHandler;
use JardisSupport\DotEnv\Handler\MatchesRawKey;
use JardisSupport\DotEnv\Exception\EnvFileNotFoundException;
use JardisSupport\DotEnv\Exception\EnvFileNotReadableException;
use JardisSupport\DotEnv\Exception\IncludeNotSupportedException;

/**
 * Parses KEY=VALUE rows into a cast value array. This is the shared engine behind file-based and
 * string-based loading: it owns comment/blank skipping, load() directive detection, _FILE secret
 * resolution, raw-key cast exemption and publishing — everything that does not require file-system
 * recursion. Include resolution itself is delegated to an injected handler; without one, a load()
 * directive is a hard error (the string-loading caller has no file-system context to resolve it).
 */
class LoadValuesFromRows
{
    private CastTypeHandler $castTypeHandler;
    private ParseLoadDirective $parseLoadDirective;
    private MatchesRawKey $matchesRawKey;

    /** @var (callable(array{path: string, optional: bool}, bool, ?string): array<string, mixed>)|null */
    private $includeHandler;

    /**
     * @param (callable(array{path: string, optional: bool}, bool, ?string): array<string, mixed>)|null $includeHandler
     */
    public function __construct(
        CastTypeHandler $castTypeHandler,
        ?ParseLoadDirective $parseLoadDirective = null,
        ?MatchesRawKey $matchesRawKey = null,
        ?callable $includeHandler = null
    ) {
        $this->castTypeHandler = $castTypeHandler;
        $this->parseLoadDirective = $parseLoadDirective ?? new ParseLoadDirective();
        $this->matchesRawKey = $matchesRawKey ?? new MatchesRawKey();
        $this->includeHandler = $includeHandler;
    }

    /**
     * @param array<string> $rows
     * @return array<string, mixed>
     * @throws EnvFileNotFoundException
     * @throws EnvFileNotReadableException
     * @throws IncludeNotSupportedException
     */
    public function __invoke(array $rows, bool $public, ?string $baseDir): array
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
                $fileResult = $this->resolveFileValue($key, $value, $public, $baseDir);
                $result = array_merge($result, $fileResult);
                continue;
            }

            $result = array_merge($result, $this->assignValue($key, $value, $public));
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
    private function resolveFileValue(string $fileKey, string $filePath, bool $public, ?string $baseDir): array
    {
        $resolvedKey = substr($fileKey, 0, -5);

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

        return $this->assignValue($resolvedKey, $value, $public);
    }

    /**
     * @return array<string, mixed>
     */
    private function assignValue(string $key, string $value, bool $public): array
    {
        $this->castTypeHandler->getRegistry()->set($key, $value);
        $typeCastValue = ($this->matchesRawKey)($key) ? $value : ($this->castTypeHandler)($value);

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
    }
}
