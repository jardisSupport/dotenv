<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv\Casting;

use InvalidArgumentException;

/**
 * This class runs all given castTypes in $convertServices
 */
class CastTypeHandler
{
    /** @var array<string|null|object> */
    private array $castTypeClasses = [
        CastStringToValue::class => null,
        CastUserHome::class => null,
        CastStringToNumeric::class => null,
        CastStringToBool::class => null,
        CastStringToArray::class => null,
    ];

    public function __invoke(?string $value = null): mixed
    {
        if ($value === null) {
            return null;
        }

        foreach ($this->castTypeClasses as $CastTypeHandlerClass => $CastTypeHandler) {
            $CastTypeHandler = $CastTypeHandler ?? new $CastTypeHandlerClass($this);
            $this->castTypeClasses[$CastTypeHandlerClass] = $CastTypeHandler;

            $value = is_callable($CastTypeHandler) ? $CastTypeHandler($value) : $value;

            if (is_array($value) || is_bool($value) || is_int($value) || is_float($value)) {
                break;
            }
        }

        return $value;
    }

    public function setCastTypeClass(string $castTypeClass): void
    {
        if (!class_exists($castTypeClass)) {
            $message = 'Cast type class "' . $castTypeClass . '" does not exist.';
            throw new InvalidArgumentException($message);
        }
        $this->castTypeClasses[$castTypeClass] = null;
    }

    public function removeCastTypeClass(string $castTypeClass): void
    {
        if (array_key_exists($castTypeClass, $this->castTypeClasses)) {
            unset($this->castTypeClasses[$castTypeClass]);
        }
    }
}
