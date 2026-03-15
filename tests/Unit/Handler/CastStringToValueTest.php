<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv\Tests\Unit\Handler;

use JardisSupport\DotEnv\Handler\CastStringToValue;
use JardisSupport\DotEnv\Handler\VariableRegistry;
use PHPUnit\Framework\TestCase;

class CastStringToValueTest extends TestCase
{
    private CastStringToValue $transformer;
    private VariableRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new VariableRegistry();
        $this->transformer = new CastStringToValue($this->registry);
    }

    public function testWithNullValue(): void
    {
        $result = ($this->transformer)(null);

        $this->assertNull($result);
    }

    public function testWithNoEnvironmentVariables(): void
    {
        $input = 'This is a ${UNKNOWN_REGISTRY_VAR} test';
        $expected = 'This is a ${UNKNOWN_REGISTRY_VAR} test';
        $result = ($this->transformer)($input);

        $this->assertEquals($expected, $result);
    }

    public function testWithRegistryVariables(): void
    {
        $this->registry->set('TEST_VAR', 'success');

        $input = 'This is a ${TEST_VAR} test';
        $expected = 'This is a success test';
        $result = ($this->transformer)($input);

        $this->assertEquals($expected, $result);
    }

    public function testWithMultipleRegistryVariables(): void
    {
        $this->registry->set('FIRST_VAR', 'first');
        $this->registry->set('SECOND_VAR', 'second');

        $input = 'This is ${FIRST_VAR} and ${SECOND_VAR}';
        $expected = 'This is first and second';
        $result = ($this->transformer)($input);

        $this->assertEquals($expected, $result);
    }

    public function testWithPartialVariables(): void
    {
        $this->registry->set('PARTIAL_VAR', 'partial');

        $input = 'This is ${PARTIAL_VAR} and ${UNKNOWN_VAR}';
        $expected = 'This is partial and ${UNKNOWN_VAR}';
        $result = ($this->transformer)($input);

        $this->assertEquals($expected, $result);
    }

    public function testFallbackToGetenv(): void
    {
        putenv('GETENV_FALLBACK_VAR=from_os');

        $input = 'Value is ${GETENV_FALLBACK_VAR}';
        $expected = 'Value is from_os';
        $result = ($this->transformer)($input);

        $this->assertEquals($expected, $result);

        putenv('GETENV_FALLBACK_VAR');
    }

    public function testRegistryTakesPrecedenceOverGetenv(): void
    {
        putenv('PRECEDENCE_VAR=from_os');
        $this->registry->set('PRECEDENCE_VAR', 'from_registry');

        $input = '${PRECEDENCE_VAR}';
        $expected = 'from_registry';
        $result = ($this->transformer)($input);

        $this->assertEquals($expected, $result);

        putenv('PRECEDENCE_VAR');
    }
}
