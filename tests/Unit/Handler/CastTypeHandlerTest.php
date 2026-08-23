<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv\Tests\Unit\Handler;

use InvalidArgumentException;
use JardisSupport\DotEnv\Handler\CastStringToValue;
use JardisSupport\DotEnv\Handler\CastTypeHandler;
use PHPUnit\Framework\TestCase;

class CastTypeHandlerTest extends TestCase
{
    private CastTypeHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new CastTypeHandler();
    }

    public function testSetCastTypeClassAppendsToEnd(): void
    {
        $this->handler->setCastTypeClass(StubCasterEnd::class);

        $result = ($this->handler)('test_value');

        $this->assertSame('test_value_end', $result);
    }

    public function testSetCastTypeClassPrependAddsToBeginning(): void
    {
        $this->handler->setCastTypeClass(StubCasterPrepend::class, prepend: true);

        $result = ($this->handler)('prepend_marker');

        $this->assertSame('PREPEND_WAS_FIRST', $result);
    }

    public function testSetCastTypeClassThrowsForNonExistentClass(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->handler->setCastTypeClass('NonExistent\\FakeClass');
    }

    public function testRemoveCastTypeClass(): void
    {
        $this->handler->removeCastTypeClass(CastStringToValue::class);

        putenv('REMOVE_TEST_VAR=hello');
        $result = ($this->handler)('${REMOVE_TEST_VAR}');

        $this->assertSame('${REMOVE_TEST_VAR}', $result);
        putenv('REMOVE_TEST_VAR');
    }

    public function testSetCastTypeInstanceAppends(): void
    {
        $stub = new StubCasterEnd();
        $this->handler->setCastTypeInstance($stub);

        $result = ($this->handler)('test_value');

        $this->assertSame('test_value_end', $result);
    }

    public function testSetCastTypeInstancePrepend(): void
    {
        $stub = new StubCasterPrepend();
        $this->handler->setCastTypeInstance($stub, prepend: true);

        $result = ($this->handler)('prepend_marker');

        $this->assertSame('PREPEND_WAS_FIRST', $result);
    }

    public function testSetCastTypeInstanceThrowsForNonInvokable(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not invokable');

        $this->handler->setCastTypeInstance(new \stdClass());
    }

    public function testNullReturnsNull(): void
    {
        $result = ($this->handler)(null);

        $this->assertNull($result);
    }

    public function testAddValueHandlerRunsInNormalChain(): void
    {
        $this->handler->addValueHandler(new StubCasterEnd());

        $result = ($this->handler)('test_value');

        $this->assertSame('test_value_end', $result);
    }

    public function testResolveRawValueSkipsCastsWithoutValueHandlers(): void
    {
        $result = $this->handler->resolveRawValue('false');

        $this->assertSame('false', $result);
    }

    public function testResolveRawValueRunsOnlyValueHandlersNotCastInstances(): void
    {
        // Registered as a plain cast instance, not as a value handler: must not run on raw values.
        $this->handler->setCastTypeInstance(new StubCasterEnd());

        $result = $this->handler->resolveRawValue('test_value');

        $this->assertSame('test_value', $result);
    }

    public function testResolveRawValueRunsValueHandlersInChainOrder(): void
    {
        $this->handler->addValueHandler(new StubValueSuffixA());
        $this->handler->addValueHandler(new StubValueSuffixB(), prepend: true);

        $result = $this->handler->resolveRawValue('x');

        // Prepended B sits before appended A in the chain.
        $this->assertSame('x_b_a', $result);
    }

    public function testResolveRawValueKeepsStringWhenHandlerReturnsNonString(): void
    {
        $this->handler->addValueHandler(new StubValueNonString());

        $result = $this->handler->resolveRawValue('false');

        $this->assertSame('false', $result);
    }

    public function testResolveRawValueNullReturnsNull(): void
    {
        $this->assertNull($this->handler->resolveRawValue(null));
    }
}

/**
 * Stub caster that appends "_end" — only triggers on a specific marker
 */
class StubCasterEnd
{
    public function __invoke(?string $value = null): ?string
    {
        if ($value === 'test_value') {
            return 'test_value_end';
        }
        return $value;
    }
}

/**
 * Value handler stub appending "_a" — proves chain order together with StubValueSuffixB
 */
class StubValueSuffixA
{
    public function __invoke(?string $value = null): ?string
    {
        return $value === null ? null : $value . '_a';
    }
}

/**
 * Value handler stub appending "_b"
 */
class StubValueSuffixB
{
    public function __invoke(?string $value = null): ?string
    {
        return $value === null ? null : $value . '_b';
    }
}

/**
 * Value handler stub returning a non-string — the raw path must keep the previous string
 */
class StubValueNonString
{
    public function __invoke(?string $value = null): bool
    {
        return false;
    }
}

/**
 * Stub caster that returns a fixed value when it sees its marker — proves it ran first
 */
class StubCasterPrepend
{
    public function __invoke(?string $value = null): ?string
    {
        if ($value === 'prepend_marker') {
            return 'PREPEND_WAS_FIRST';
        }
        return $value;
    }
}
