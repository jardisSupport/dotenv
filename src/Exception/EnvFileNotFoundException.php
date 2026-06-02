<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv\Exception;

/**
 * Exception thrown when a required .env file is not found
 */
class EnvFileNotFoundException extends DotEnvException
{
    private string $filePath;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;

        $message = sprintf('Environment file not found: %s', $filePath);

        parent::__construct($message);
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }
}
