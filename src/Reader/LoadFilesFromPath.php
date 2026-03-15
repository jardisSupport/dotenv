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
     * @param string|null $appEnv
     * @return array<string>
     */
    public function __invoke(string $pathToEnvFile, ?string $appEnv = null): array
    {
        return array_merge(
            $this->getBaseFiles($pathToEnvFile),
            $appEnv !== null ? $this->getEnvFiles($pathToEnvFile, $appEnv) : []
        );
    }

    /**
     * @return array<string>
     */
    public function getBaseFiles(string $pathToEnvFile): array
    {
        return $this->filterExisting($pathToEnvFile, ['/.env', '/.env.local']);
    }

    /**
     * @return array<string>
     */
    public function getEnvFiles(string $pathToEnvFile, string $appEnv): array
    {
        return $this->filterExisting($pathToEnvFile, [
            '/.env.' . $appEnv,
            '/.env.' . $appEnv . '.local'
        ]);
    }

    /**
     * @param array<string> $suffixes
     * @return array<string>
     */
    private function filterExisting(string $basePath, array $suffixes): array
    {
        $files = [];

        foreach ($suffixes as $suffix) {
            $file = $basePath . $suffix;
            if (file_exists($file)) {
                $files[] = $file;
            }
        }

        return $files;
    }
}
