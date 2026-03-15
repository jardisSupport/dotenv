<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv\Handler;

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
        CastStringToJson::class => null,
        CastStringToArray::class => null,
    ];

    private VariableRegistry $registry;

    public function __construct(?VariableRegistry $registry = null)
    {
        $this->registry = $registry ?? new VariableRegistry();
    }

    public function __invoke(?string $value = null): mixed
    {
        if ($value === null) {
            return null;
        }

        foreach ($this->castTypeClasses as $CastTypeHandlerClass => $CastTypeHandler) {
            $CastTypeHandler = $CastTypeHandler ?? $this->createInstance($CastTypeHandlerClass);
            $this->castTypeClasses[$CastTypeHandlerClass] = $CastTypeHandler;

            $value = is_callable($CastTypeHandler) ? $CastTypeHandler($value) : $value;

            if (is_array($value) || is_bool($value) || is_int($value) || is_float($value)) {
                break;
            }
        }

        return $value;
    }

    public function getRegistry(): VariableRegistry
    {
        return $this->registry;
    }

    public function setCastTypeClass(string $castTypeClass, bool $prepend = false): void
    {
        if (!class_exists($castTypeClass)) {
            $message = 'Cast type class "' . $castTypeClass . '" does not exist.';
            throw new InvalidArgumentException($message);
        }

        if ($prepend) {
            $this->castTypeClasses = [$castTypeClass => null] + $this->castTypeClasses;
        } else {
            $this->castTypeClasses[$castTypeClass] = null;
        }
    }

    public function setCastTypeInstance(object $instance, bool $prepend = false): void
    {
        if (!is_callable($instance)) {
            $message = 'Cast type instance "' . get_class($instance) . '" is not invokable.';
            throw new InvalidArgumentException($message);
        }

        $key = get_class($instance);

        if ($prepend) {
            $this->castTypeClasses = [$key => $instance] + $this->castTypeClasses;
        } else {
            $this->castTypeClasses[$key] = $instance;
        }
    }

    public function removeCastTypeClass(string $castTypeClass): void
    {
        if (array_key_exists($castTypeClass, $this->castTypeClasses)) {
            unset($this->castTypeClasses[$castTypeClass]);
        }
    }

    private function createInstance(string $class): object
    {
        if ($class === CastStringToValue::class || $class === CastUserHome::class) {
            return new $class($this->registry);
        }

        if ($class === CastStringToNumeric::class || $class === CastStringToBool::class) {
            return new $class();
        }

        return new $class($this);
    }
}
