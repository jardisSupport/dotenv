<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv\Reader;

use JardisSupport\DotEnv\Handler\CastTypeHandler;
use JardisSupport\DotEnv\Exception\CircularEnvIncludeException;
use JardisSupport\DotEnv\Exception\EnvFileNotFoundException;
use JardisSupport\DotEnv\Exception\EnvFileNotReadableException;

/**
 * Reads and return all values from given files
 * Supports load() and load?() directives for including other .env files
 */
class LoadValuesFromFiles
{
    private CastTypeHandler $castTypeHandler;
    private ParseLoadDirective $parseLoadDirective;

    /** @var array<string> Stack of files currently being loaded for circular reference detection */
    private array $includeStack = [];

    public function __construct(
        CastTypeHandler $castTypeHandler,
        ?ParseLoadDirective $parseLoadDirective = null
    ) {
        $this->castTypeHandler = $castTypeHandler;
        $this->parseLoadDirective = $parseLoadDirective ?? new ParseLoadDirective();
    }

    /**
     * @param array<string> $files
     * @param bool|null $public
     * @return array<string, mixed>
     * @throws CircularEnvIncludeException
     * @throws EnvFileNotFoundException
     * @throws EnvFileNotReadableException
     */
    public function __invoke(array $files, ?bool $public = true): array
    {
        $envValues = [];
        $public = $public ?? true;

        // Reset include stack for each top-level invocation
        $this->includeStack = [];

        foreach ($files as $file) {
            if (file_exists($file)) {
                $envValues = array_merge($envValues, $this->loadFile($file, $public));
            }
        }

        return $envValues;
    }

    /**
     * Load a single file with include support
     *
     * @return array<string, mixed>
     * @throws CircularEnvIncludeException
     * @throws EnvFileNotFoundException
     * @throws EnvFileNotReadableException
     */
    private function loadFile(string $file, bool $public): array
    {
        $realPath = realpath($file);

        if ($realPath === false) {
            return [];
        }

        // Check for circular reference
        if (in_array($realPath, $this->includeStack, true)) {
            throw new CircularEnvIncludeException($realPath, $this->includeStack);
        }

        // Add to stack before processing
        $this->includeStack[] = $realPath;

        try {
            $rows = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            if ($rows === false) {
                throw new EnvFileNotReadableException($file);
            }

            return $this->loadFileValues($rows, $public, dirname($realPath));
        } finally {
            // Remove from stack after processing
            array_pop($this->includeStack);
        }
    }

    /**
     * @param array<string> $rows
     * @param bool $public
     * @param string $baseDir Directory of the current file for resolving relative paths
     * @return array<string, mixed>
     * @throws CircularEnvIncludeException
     * @throws EnvFileNotFoundException
     * @throws EnvFileNotReadableException
     */
    protected function loadFileValues(array $rows, bool $public, string $baseDir): array
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
                $includeResult = $this->processInclude($loadDirective, $public, $baseDir);
                $result = array_merge($result, $includeResult);
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

            $this->castTypeHandler->getRegistry()->set($key, $value);
            $typeCastValue = ($this->castTypeHandler)($value);

            if ($public) {
                $this->publish($key, $value, $typeCastValue);
            } else {
                $result[$key] = $typeCastValue;
            }
        }

        return $result;
    }

    /**
     * Process a load() or load?() directive
     *
     * @param array{path: string, optional: bool} $directive
     * @param bool $public
     * @param string $baseDir Directory of the including file
     * @return array<string, mixed>
     * @throws CircularEnvIncludeException
     * @throws EnvFileNotFoundException
     * @throws EnvFileNotReadableException
     */
    private function processInclude(array $directive, bool $public, string $baseDir): array
    {
        $includePath = $this->resolveIncludePath($directive['path'], $baseDir);

        // Check if base file exists
        if (!file_exists($includePath)) {
            if ($directive['optional']) {
                return [];
            }
            throw new EnvFileNotFoundException($includePath);
        }

        if (!is_readable($includePath)) {
            throw new EnvFileNotReadableException($includePath);
        }

        // Build cascade: base → .local → .{APP_ENV} → .{APP_ENV}.local
        $cascadeFiles = $this->buildCascadeFiles($includePath);

        $result = [];
        foreach ($cascadeFiles as $file) {
            if (file_exists($file) && is_readable($file)) {
                $result = array_merge($result, $this->loadFile($file, $public));
            }
        }

        return $result;
    }

    /**
     * Build cascade file list for an include path.
     *
     * @return array<string>
     */
    private function buildCascadeFiles(string $basePath): array
    {
        $files = [$basePath, $basePath . '.local'];

        $appEnv = $this->castTypeHandler->getRegistry()->get('APP_ENV');
        if ($appEnv !== null && $appEnv !== '') {
            $files[] = $basePath . '.' . $appEnv;
            $files[] = $basePath . '.' . $appEnv . '.local';
        }

        return $files;
    }

    /**
     * Resolve include path relative to the base directory
     *
     * @param string $path The path from the load directive
     * @param string $baseDir The directory of the including file
     * @return string The resolved absolute path
     */
    private function resolveIncludePath(string $path, string $baseDir): string
    {
        // If absolute path, use as-is
        if (strpos($path, '/') === 0) {
            return $path;
        }

        // Relative path - resolve relative to base directory
        return $baseDir . '/' . $path;
    }

    /**
     * Resolve a _FILE key: read the file and register under the key without _FILE suffix.
     *
     * @return array<string, mixed>
     * @throws EnvFileNotFoundException
     * @throws EnvFileNotReadableException
     */
    private function resolveFileValue(string $fileKey, string $filePath, bool $public, string $baseDir): array
    {
        $resolvedKey = substr($fileKey, 0, -5);

        // Resolve relative paths from the including file's directory
        if ($filePath !== '' && $filePath[0] !== '/') {
            $filePath = $baseDir . '/' . $filePath;
        }

        if (!file_exists($filePath)) {
            throw new EnvFileNotFoundException($filePath);
        }

        if (!is_readable($filePath)) {
            throw new EnvFileNotReadableException($filePath);
        }

        $value = trim(file_get_contents($filePath) ?: '');

        $this->castTypeHandler->getRegistry()->set($resolvedKey, $value);
        $typeCastValue = ($this->castTypeHandler)($value);

        if ($public) {
            $this->publish($resolvedKey, $value, $typeCastValue);
            return [];
        }

        return [$resolvedKey => $typeCastValue];
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
     * This intentional difference between putenv()/getenv() and $_ENV/$_SERVER is a technical
     * constraint of POSIX environment variables and cannot be resolved without lossy conversions.
     * Callers that need typed values should read from $_ENV or $_SERVER; callers that rely on
     * getenv() will always receive strings and must cast manually if needed.
     *
     * @param string $key      The environment variable name.
     * @param string $value    The raw string value (as read from the .env file).
     * @param mixed  $castValue The type-cast value after the full handler chain.
     */
    protected function publish(string $key, string $value, mixed $castValue): void
    {
        $publishValue = is_array($castValue) ? $value : $castValue;
        putenv("$key=$value");
        $_ENV[$key] = $publishValue;
        $_SERVER[$key] = $publishValue;
    }
}
