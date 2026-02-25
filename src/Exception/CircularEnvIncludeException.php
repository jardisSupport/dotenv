<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv\Exception;

/**
 * Exception thrown when a circular include is detected in .env files
 */
class CircularEnvIncludeException extends DotEnvException
{
    /** @var array<string> */
    private array $includeStack;

    /**
     * @param string $file The file that caused the circular reference
     * @param array<string> $includeStack The stack of files currently being loaded
     */
    public function __construct(string $file, array $includeStack)
    {
        $this->includeStack = $includeStack;

        $stackTrace = implode(' -> ', $includeStack) . ' -> ' . $file;
        $message = sprintf('Circular include detected: %s', $stackTrace);

        parent::__construct($message);
    }

    /**
     * @return array<string>
     */
    public function getIncludeStack(): array
    {
        return $this->includeStack;
    }
}
