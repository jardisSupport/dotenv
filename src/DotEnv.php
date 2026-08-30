<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv;

use JardisSupport\DotEnv\Handler\CastTypeHandler;
use JardisSupport\DotEnv\Handler\MatchesRawKey;
use JardisSupport\DotEnv\Handler\SourceRegistry;
use JardisSupport\DotEnv\Exception\CircularEnvIncludeException;
use JardisSupport\DotEnv\Exception\EnvFileNotFoundException;
use JardisSupport\DotEnv\Exception\EnvFileNotReadableException;
use JardisSupport\DotEnv\Exception\IncludeNotSupportedException;
use JardisSupport\DotEnv\Reader\LoadFilesFromPath;
use JardisSupport\DotEnv\Reader\LoadValuesFromFiles;
use JardisSupport\DotEnv\Reader\LoadValuesFromString;
use JardisSupport\Contract\DotEnv\DotEnvInterface;

/**
 * The DotEnv class provides loading and processing environment variables from .env files for public and private
 */
class DotEnv implements DotEnvInterface
{
    private LoadFilesFromPath $loadFilesFromPath;
    private LoadValuesFromFiles $loadValuesFromFiles;
    private LoadValuesFromString $loadValuesFromString;
    private CastTypeHandler $castTypeHandler;
    private MatchesRawKey $matchesRawKey;
    private SourceRegistry $sourceRegistry;

    public function __construct(
        ?LoadFilesFromPath $fileFinder = null,
        ?LoadValuesFromFiles $fileContentReader = null,
        ?LoadValuesFromString $stringContentReader = null,
        ?MatchesRawKey $matchesRawKey = null,
        ?SourceRegistry $sourceRegistry = null
    ) {
        $this->loadFilesFromPath = $fileFinder ?? new LoadFilesFromPath();
        $this->castTypeHandler = new CastTypeHandler();
        $this->matchesRawKey = $matchesRawKey ?? new MatchesRawKey();
        $this->sourceRegistry = $sourceRegistry ?? new SourceRegistry();
        $this->loadValuesFromFiles = $fileContentReader ?? new LoadValuesFromFiles(
            $this->castTypeHandler,
            null,
            $this->matchesRawKey,
            null,
            $this->sourceRegistry
        );
        $this->loadValuesFromString = $stringContentReader ?? new LoadValuesFromString(
            $this->castTypeHandler,
            $this->matchesRawKey,
            null,
            $this->sourceRegistry
        );
    }

    /**
     * Loads and processes environment files from the specified path.
     * Two-stage loading: base files first, then APP_ENV-specific files.
     *
     * @param string $pathToEnvFiles The path to the directory containing the environment files to be loaded.
     * @return void
     * @throws CircularEnvIncludeException
     * @throws EnvFileNotFoundException
     * @throws EnvFileNotReadableException
     */
    public function loadPublic(string $pathToEnvFiles): void
    {
        // Stage 1: Load .env and .env.local
        $baseFiles = $this->loadFilesFromPath->getBaseFiles($pathToEnvFiles);
        ($this->loadValuesFromFiles)($baseFiles);

        // Stage 2: APP_ENV is now available (from stage 1 or OS environment)
        $appEnv = $this->resolveAppEnv();
        if ($appEnv !== null) {
            $envFiles = $this->loadFilesFromPath->getEnvFiles($pathToEnvFiles, $appEnv);
            ($this->loadValuesFromFiles)($envFiles);
        }
    }

    /**
     * Loads private environment files and their values from the specified path.
     * Two-stage loading: base files first, then APP_ENV-specific files.
     *
     * @param string $pathToEnvFiles The path to the directory containing environment files.
     * @return array<string, mixed> Returns the loaded environment values.
     * @throws CircularEnvIncludeException
     * @throws EnvFileNotFoundException
     * @throws EnvFileNotReadableException
     */
    public function loadPrivate(string $pathToEnvFiles): array
    {
        // Stage 1: Load .env and .env.local
        $baseFiles = $this->loadFilesFromPath->getBaseFiles($pathToEnvFiles);
        $result = ($this->loadValuesFromFiles)($baseFiles, false);

        // Stage 2: APP_ENV from parsed result, $_ENV, or OS environment
        $appEnv = $this->resolveAppEnvFromResult($result);
        if ($appEnv !== null) {
            $envFiles = $this->loadFilesFromPath->getEnvFiles($pathToEnvFiles, $appEnv);
            $envResult = ($this->loadValuesFromFiles)($envFiles, false);
            $result = array_merge($result, $envResult);
        }

        return $result;
    }

    public function addHandler(object $handler, bool $prepend = false): void
    {
        $this->castTypeHandler->addValueHandler($handler, $prepend);
    }

    public function removeHandler(string $handlerClass): void
    {
        $this->castTypeHandler->removeCastTypeClass($handlerClass);
    }

    /**
     * Registers keys/suffixes (case-insensitive) whose values skip the built-in casts and
     * survive as raw strings — e.g. credential suffixes like `_PASSWORD` where `false`/`123456`
     * must not become bool/int. Handlers registered via addHandler() still run for these keys
     * (raw means cast-free, not handler-free). Accumulates and de-duplicates; there is no remove.
     *
     * @param array<string> $keysOrSuffixes
     */
    public function addRawKeys(array $keysOrSuffixes): void
    {
        $this->matchesRawKey->addRawKeys($keysOrSuffixes);
    }

    /**
     * Loads .env-formatted content from a string and publishes it like loadPublic() does.
     * No APP_ENV cascade; a load()/load?() directive is a hard error (no file-system context).
     *
     * @param string $content .env-formatted content (e.g. from a secrets manager)
     * @param string|null $baseDir Base directory for resolving relative KEY_FILE paths; required
     *                             if the content contains a KEY_FILE with a relative path
     * @throws IncludeNotSupportedException
     * @throws EnvFileNotFoundException
     * @throws EnvFileNotReadableException
     */
    public function loadPublicFromString(string $content, ?string $baseDir = null): void
    {
        ($this->loadValuesFromString)($content, true, $baseDir);
    }

    /**
     * Loads .env-formatted content from a string and returns an isolated array like
     * loadPrivate() does. No APP_ENV cascade; a load()/load?() directive is a hard error.
     *
     * @param string $content .env-formatted content (e.g. from a secrets manager)
     * @param string|null $baseDir Base directory for resolving relative KEY_FILE paths; required
     *                             if the content contains a KEY_FILE with a relative path
     * @return array<string, mixed>
     * @throws IncludeNotSupportedException
     * @throws EnvFileNotFoundException
     * @throws EnvFileNotReadableException
     */
    public function loadPrivateFromString(string $content, ?string $baseDir = null): array
    {
        return ($this->loadValuesFromString)($content, false, $baseDir);
    }

    /**
     * Returns the origin of the last value assigned to each key across every load call on this
     * instance: `env` (process environment), `file:<realpath>` (that file) or `string` (string
     * input). A `KEY_FILE=...` line reports the origin of the line, not of the secret file.
     * Never contains values, only origins. Accumulates; it is not reset per load call.
     *
     * A reader injected through the constructor carries its own SourceRegistry unless the same
     * instance is passed here too — then it does not feed this result.
     *
     * @return array<string, string> Key => origin of the winning value
     */
    public function sources(): array
    {
        return $this->sourceRegistry->all();
    }

    private function resolveAppEnv(): ?string
    {
        $appEnv = $_ENV['APP_ENV'] ?? (getenv('APP_ENV') ?: null);

        return is_string($appEnv) && $appEnv !== '' ? $appEnv : null;
    }

    /**
     * @param array<string, mixed> $result
     */
    private function resolveAppEnvFromResult(array $result): ?string
    {
        $appEnv = $result['APP_ENV'] ?? $_ENV['APP_ENV'] ?? (getenv('APP_ENV') ?: null);

        return is_string($appEnv) && $appEnv !== '' ? $appEnv : null;
    }
}
