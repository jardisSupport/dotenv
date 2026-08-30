<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv\Reader;

use JardisSupport\DotEnv\Handler\CastTypeHandler;
use JardisSupport\DotEnv\Handler\MatchesRawKey;
use JardisSupport\DotEnv\Handler\SourceRegistry;
use JardisSupport\DotEnv\Exception\CircularEnvIncludeException;
use JardisSupport\DotEnv\Exception\EnvFileNotFoundException;
use JardisSupport\DotEnv\Exception\EnvFileNotReadableException;

/**
 * Reads and return all values from given files
 * Supports load() and load?() directives for including other .env files
 *
 * Each file passes its own realpath as origin to the row engine, so an included file records
 * `file:<realpath of the include>`, not the one of the including file.
 */
class LoadValuesFromFiles
{
    private CastTypeHandler $castTypeHandler;
    private ParseLoadDirective $parseLoadDirective;
    private LoadValuesFromRows $loadValuesFromRows;

    /** @var array<string> Stack of files currently being loaded for circular reference detection */
    private array $includeStack = [];

    public function __construct(
        CastTypeHandler $castTypeHandler,
        ?ParseLoadDirective $parseLoadDirective = null,
        ?MatchesRawKey $matchesRawKey = null,
        ?LoadValuesFromRows $loadValuesFromRows = null,
        ?SourceRegistry $sourceRegistry = null
    ) {
        $this->castTypeHandler = $castTypeHandler;
        $this->parseLoadDirective = $parseLoadDirective ?? new ParseLoadDirective();
        $this->loadValuesFromRows = $loadValuesFromRows ?? new LoadValuesFromRows(
            $castTypeHandler,
            $this->parseLoadDirective,
            $matchesRawKey,
            fn(array $directive, bool $public, ?string $baseDir): array =>
                $this->processInclude($directive, $public, (string) $baseDir),
            null,
            $sourceRegistry
        );
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

            return $this->loadFileValues(
                $rows,
                $public,
                dirname($realPath),
                SourceRegistry::SOURCE_FILE_PREFIX . $realPath
            );
        } finally {
            // Remove from stack after processing
            array_pop($this->includeStack);
        }
    }

    /**
     * @param array<string> $rows
     * @param bool $public
     * @param string $baseDir Directory of the current file for resolving relative paths
     * @param string|null $origin Origin recorded for these rows, i.e. `file:<realpath of the file>`
     * @return array<string, mixed>
     * @throws CircularEnvIncludeException
     * @throws EnvFileNotFoundException
     * @throws EnvFileNotReadableException
     */
    protected function loadFileValues(array $rows, bool $public, string $baseDir, ?string $origin = null): array
    {
        return ($this->loadValuesFromRows)($rows, $public, $baseDir, $origin);
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
}
