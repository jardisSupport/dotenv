<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv;

use JardisSupport\DotEnv\Handler\CastTypeHandler;
use JardisSupport\DotEnv\Exception\CircularEnvIncludeException;
use JardisSupport\DotEnv\Exception\EnvFileNotFoundException;
use JardisSupport\DotEnv\Exception\EnvFileNotReadableException;
use JardisSupport\DotEnv\Reader\LoadFilesFromPath;
use JardisSupport\DotEnv\Reader\LoadValuesFromFiles;
use JardisPort\DotEnv\DotEnvInterface;

/**
 * The DotEnv class provides loading and processing environment variables from .env files for public and private
 */
class DotEnv implements DotEnvInterface
{
    private LoadFilesFromPath $loadFilesFromPath;
    private LoadValuesFromFiles $loadValuesFromFiles;
    private CastTypeHandler $CastTypeHandler;

    public function __construct(
        ?LoadFilesFromPath $fileFinder = null,
        ?LoadValuesFromFiles $fileContentReader = null
    ) {
        $this->loadFilesFromPath = $fileFinder ?? new LoadFilesFromPath();
        $this->CastTypeHandler = new CastTypeHandler();
        $this->loadValuesFromFiles = $fileContentReader ?? new LoadValuesFromFiles($this->CastTypeHandler);
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
        $this->CastTypeHandler->setCastTypeInstance($handler, $prepend);
    }

    public function removeHandler(string $handlerClass): void
    {
        $this->CastTypeHandler->removeCastTypeClass($handlerClass);
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
