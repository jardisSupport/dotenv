<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv\Casting;

/**
 * Type cast string to numeric
 */
class CastStringToNumeric
{
    /**
     * @return int|float|string|null
     */
    public function __invoke(?string $value = null): int|float|string|null
    {
        return is_numeric($value) ? $value + 0 : $value;
    }
}
