<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv\Exception;

/**
 * Exception thrown when a .env file contains invalid syntax
 */
class EnvParseException extends DotEnvException
{
    private string $filePath;
    private int $lineNumber;

    public function __construct(string $filePath, int $lineNumber, string $reason)
    {
        $this->filePath = $filePath;
        $this->lineNumber = $lineNumber;

        $message = sprintf('Parse error in %s on line %d: %s', $filePath, $lineNumber, $reason);

        parent::__construct($message);
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function getLineNumber(): int
    {
        return $this->lineNumber;
    }
}
