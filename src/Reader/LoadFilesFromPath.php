<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv\Reader;

/**
 * Return full qualified fileNames
 */
class LoadFilesFromPath
{
    /**
     * @param string $pathToEnvFile
     * @return array<string>
     */
    public function __invoke(string $pathToEnvFile): array
    {
        $filesToLoad = [];
        $appEnv = $_ENV['APP_ENV'] ?? null;
        $envTypes = ['/.env', '/.env.local'];

        if (!empty($appEnv)) {
            $envTypes = array_merge($envTypes, [
                '/.env.' . $appEnv,
                '/.env.' . $appEnv . '.local'
            ]);
        }

        foreach ($envTypes as $envType) {
            $file = $pathToEnvFile . $envType;
            if (file_exists($file)) {
                $filesToLoad[] = $file;
            }
        }

        return $filesToLoad;
    }
}
