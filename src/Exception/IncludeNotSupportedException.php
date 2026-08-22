<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv\Exception;

/**
 * Exception thrown when a load()/load?() directive appears while loading from a string.
 * String input has no file-system context to resolve includes against.
 */
class IncludeNotSupportedException extends DotEnvException
{
    private string $path;

    public function __construct(string $path)
    {
        $this->path = $path;

        $message = sprintf('load() directive is not supported when loading from a string: %s', $path);

        parent::__construct($message);
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
