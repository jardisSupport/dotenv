<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv\Exception;

/**
 * Exception thrown when a .env file exists but cannot be read
 */
class EnvFileNotReadableException extends DotEnvException
{
    private string $filePath;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;

        $message = sprintf('Environment file is not readable: %s', $filePath);

        parent::__construct($message);
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }
}
