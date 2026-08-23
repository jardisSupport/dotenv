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

    /** @var array<string, true> Class names registered as value handlers via addValueHandler() */
    private array $valueHandlerClasses = [];

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

        foreach ($this->castTypeClasses as $castTypeHandlerClass => $castTypeHandler) {
            $castTypeHandler = $castTypeHandler ?? $this->createInstance($castTypeHandlerClass);
            $this->castTypeClasses[$castTypeHandlerClass] = $castTypeHandler;

            $value = is_callable($castTypeHandler) ? $castTypeHandler($value) : $value;

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

    /**
     * Registers an invokable instance as a value handler: it takes its chain position like any
     * cast, but is additionally applied to raw-key values, where the plain casts are skipped.
     */
    public function addValueHandler(object $instance, bool $prepend = false): void
    {
        $this->setCastTypeInstance($instance, $prepend);
        $this->valueHandlerClasses[get_class($instance)] = true;
    }

    /**
     * Applies only the registered value handlers (in chain order) to a raw-key value. The
     * built-in and setCastTypeClass()-registered casts are skipped, so the value stays a string.
     */
    public function resolveRawValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        foreach ($this->castTypeClasses as $castTypeHandlerClass => $castTypeHandler) {
            if (!isset($this->valueHandlerClasses[$castTypeHandlerClass])) {
                continue;
            }

            $result = is_callable($castTypeHandler) ? $castTypeHandler($value) : $value;
            $value = is_string($result) ? $result : $value;
        }

        return $value;
    }

    public function removeCastTypeClass(string $castTypeClass): void
    {
        if (array_key_exists($castTypeClass, $this->castTypeClasses)) {
            unset($this->castTypeClasses[$castTypeClass]);
        }

        unset($this->valueHandlerClasses[$castTypeClass]);
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
