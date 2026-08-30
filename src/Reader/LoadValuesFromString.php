<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv\Reader;

use JardisSupport\DotEnv\Handler\CastTypeHandler;
use JardisSupport\DotEnv\Handler\MatchesRawKey;
use JardisSupport\DotEnv\Handler\SourceRegistry;
use JardisSupport\DotEnv\Exception\EnvFileNotFoundException;
use JardisSupport\DotEnv\Exception\EnvFileNotReadableException;
use JardisSupport\DotEnv\Exception\IncludeNotSupportedException;

/**
 * Reads .env-formatted content from a string instead of a file (e.g. a secret manager payload).
 * Reuses the LoadValuesFromRows engine by composition; no APP_ENV cascade and no load() support —
 * a string has no file-system context to resolve includes against.
 */
class LoadValuesFromString
{
    private LoadValuesFromRows $loadValuesFromRows;

    public function __construct(
        CastTypeHandler $castTypeHandler,
        ?MatchesRawKey $matchesRawKey = null,
        ?LoadValuesFromRows $loadValuesFromRows = null,
        ?SourceRegistry $sourceRegistry = null
    ) {
        $this->loadValuesFromRows = $loadValuesFromRows ?? new LoadValuesFromRows(
            $castTypeHandler,
            null,
            $matchesRawKey,
            null,
            null,
            $sourceRegistry
        );
    }

    /**
     * @return array<string, mixed>
     * @throws EnvFileNotFoundException
     * @throws EnvFileNotReadableException
     * @throws IncludeNotSupportedException
     */
    public function __invoke(string $content, bool $public, ?string $baseDir = null): array
    {
        $rows = $this->splitRows($this->stripBom($content));

        return ($this->loadValuesFromRows)($rows, $public, $baseDir, SourceRegistry::SOURCE_STRING);
    }

    private function stripBom(string $content): string
    {
        $bom = "\xEF\xBB\xBF";

        return str_starts_with($content, $bom) ? substr($content, strlen($bom)) : $content;
    }

    /**
     * @return array<string>
     */
    private function splitRows(string $content): array
    {
        $rows = preg_split('/\R/', $content);
        $rows = $rows === false ? [] : $rows;

        // FILE_SKIP_EMPTY_LINES parity: only rows that are exactly empty are dropped.
        return array_values(array_filter($rows, static fn(string $row): bool => $row !== ''));
    }
}
