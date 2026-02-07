<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv;

use Exception;
use JardisSupport\DotEnv\Casting\CastTypeHandler;
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
        ?LoadValuesFromFiles $fileContentReader = null,
        ?CastTypeHandler $CastTypeHandler = null
    ) {
        $this->loadFilesFromPath = $fileFinder ?? new LoadFilesFromPath();
        $this->CastTypeHandler = $CastTypeHandler ?? new CastTypeHandler();
        $this->loadValuesFromFiles = $fileContentReader ?? new LoadValuesFromFiles($this->CastTypeHandler);
    }

    /**
     * Loads and processes environment files from the specified path.
     *
     * @param string $pathToEnvFiles The path to the directory containing the environment files to be loaded.
     * @return void
     * @throws Exception
     */
    public function loadPublic(string $pathToEnvFiles): void
    {
        $filesToLoad = ($this->loadFilesFromPath)($pathToEnvFiles);
        ($this->loadValuesFromFiles)($filesToLoad);
    }

    /**
     * Loads private environment files and their values from the specified path.
     *
     * @param string $pathToEnvFiles The path to the directory containing environment files.
     * @return mixed Returns the loaded environment values.
     * @throws Exception
     */
    public function loadPrivate(string $pathToEnvFiles): mixed
    {
        $filesToLoad = ($this->loadFilesFromPath)($pathToEnvFiles);

        return ($this->loadValuesFromFiles)($filesToLoad, false);
    }
}
