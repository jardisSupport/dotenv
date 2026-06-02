<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv\Reader;

/**
 * Parses load() and load?() directives from .env file lines
 *
 * Syntax:
 * - load(.env.database)     - Required include
 * - load?(.env.local)       - Optional include (silent skip if missing)
 * - load("path with spaces/.env") - Quoted paths supported
 */
class ParseLoadDirective
{
    /**
     * Parse a line and extract load directive information
     *
     * @param string $line The line to parse
     * @return array{path: string, optional: bool}|null Returns directive info or null if not a load directive
     */
    public function __invoke(string $line): ?array
    {
        $line = trim($line);

        // Match load() or load?() with optional quotes around the path
        // Pattern: load[?]( ["|'] path ["|'] )
        $pattern = '/^load(\?)?\\((["\']?)(.+?)\\2\\)$/';

        if (preg_match($pattern, $line, $matches) !== 1) {
            return null;
        }

        $optional = $matches[1] === '?';
        $path = $matches[3];

        // Empty path is invalid
        if (trim($path) === '') {
            return null;
        }

        return [
            'path' => $path,
            'optional' => $optional,
        ];
    }
}
