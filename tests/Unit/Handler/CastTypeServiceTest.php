<?php

namespace JardisSupport\DotEnv\Tests\Unit\Handler;

use InvalidArgumentException;
use JardisSupport\DotEnv\Handler\CastTypeHandler;
use PHPUnit\Framework\TestCase;

class CastTypeServiceTest extends TestCase
{
    private CastTypeHandler $CastTypeHandler;
    protected function setUp(): void
    {
        $this->CastTypeHandler = new CastTypeHandler();
    }

    public function testWithNullValue(): void
    {
        $result = ($this->CastTypeHandler)(null);

        $this->assertNull($result);
    }

    public function testWithIntegerValue(): void
    {
        $result = ($this->CastTypeHandler)('123');

        $this->assertSame(123, $result);
        $this->assertIsInt($result);
    }

    public function testWithFloatValue(): void
    {
        $result = ($this->CastTypeHandler)('123.123');

        $this->assertSame(123.123, $result);
        $this->assertIsFloat($result);
    }

    public function testWithBooleanValue(): void
    {
        $result = ($this->CastTypeHandler)('true');

        $this->assertSame(true, $result);
        $this->assertIsBool($result);
    }

    public function testWithStingArrayValue(): void
    {
        $result = ($this->CastTypeHandler)('[1,2,3,4,5]');

        $this->assertEquals([1,2,3,4,5], $result);
        $this->assertIsArray($result);
    }

    public function testWithStingValue(): void
    {
        $result = ($this->CastTypeHandler)('testValue');

        $this->assertSame('testValue', $result);
    }

    public function testSetCastTypeClassThrowsExceptionForInvalidClass(): void
    {
        $handler = new CastTypeHandler();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cast type class "NonExistentClass" does not exist.');

        $handler->setCastTypeClass('NonExistentClass');
    }

    public function testSetCastTypeClassAddsClassSuccessfully(): void
    {
        $handler = new CastTypeHandler();

        $handler->setCastTypeClass(MockCastTypeHandler::class);

        $reflector = new \ReflectionClass($handler);
        $property = $reflector->getProperty('castTypeClasses');
        $property->setAccessible(true);

        $castTypeClasses = $property->getValue($handler);

        $this->assertArrayHasKey(MockCastTypeHandler::class, $castTypeClasses, 'Die Klasse sollte erfolgreich registriert worden sein.');
    }

    public function testRemoveCastTypeClassRemovesSuccessfully(): void
    {
        $handler = new CastTypeHandler();

        $handler->setCastTypeClass(MockCastTypeHandler::class);
        $handler->removeCastTypeClass(MockCastTypeHandler::class);

        $reflector = new \ReflectionClass($handler);
        $property = $reflector->getProperty('castTypeClasses');
        $property->setAccessible(true);

        $castTypeClasses = $property->getValue($handler);

        $this->assertArrayNotHasKey(MockCastTypeHandler::class, $castTypeClasses, 'Die Klasse sollte erfolgreich entfernt worden sein.');
    }

    public function testRemoveCastTypeClassDoesNotFailForNonExistentClass(): void
    {
        $handler = new CastTypeHandler();

        $handler->removeCastTypeClass('NonExistentClass');

        $this->assertTrue(true, 'Das Entfernen einer nicht vorhandenen Klasse sollte keinen Fehler erzeugen.');
    }
}

class MockCastTypeHandler
{
    public function __invoke($value)
    {
        return $value;
    }
}
