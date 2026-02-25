<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv\Reader;

use JardisSupport\DotEnv\Casting\CastTypeHandler;
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
            $value = $value ? trim($value) : $value;

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

        $appEnv = $_ENV['APP_ENV'] ?? null;
        if (!empty($appEnv)) {
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
     * @param string $key
     * @param string $value
     * @param mixed $castValue
     */
    protected function publish(string $key, string $value, mixed $castValue): void
    {
        $value = is_array($castValue) ? $value : $castValue;
        putenv("$key=$value");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
