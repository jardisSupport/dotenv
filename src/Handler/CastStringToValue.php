<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv\Handler;

/**
 * Transforms all string vars to values based on environment values
 */
class CastStringToValue
{
    private VariableRegistry $registry;

    public function __construct(VariableRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function __invoke(?string $value = null): ?string
    {
        if ($value === null) {
            return null;
        }

        return preg_replace_callback(
            '/\${([^}]+)}/',
            function ($matches) {
                $varName = $matches[1];
                return $this->registry->get($varName) ?? $matches[0];
            },
            $value
        ) ?? $value;
    }
}
