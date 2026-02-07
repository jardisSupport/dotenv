<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv\Casting;

/**
 * Type cast string to bool
 */
class CastStringToBool
{
    /**
     * @param string|null $value
     * @return bool|string|null
     */
    public function __invoke(?string $value = null): bool|string|null
    {
        if (is_null($value)) {
            return null;
        }

        $result = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $result ?? $value;
    }
}
