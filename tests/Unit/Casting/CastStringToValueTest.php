<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv\Tests\Unit\Casting;

use JardisSupport\DotEnv\Casting\CastStringToValue;
use PHPUnit\Framework\TestCase;

class CastStringToValueTest extends TestCase
{
    private CastStringToValue $transformer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transformer = new CastStringToValue();
    }

    public function testWithNullValue(): void
    {
        $result = ($this->transformer)(null);

        $this->assertNull($result);
    }

    public function testWithNoEnvironmentVariables(): void
    {
        $input = 'This is a ${VAR} test';
        $expected = 'This is a ${VAR} test';
        $result =($this->transformer)($input);

        $this->assertEquals($expected, $result);
    }

    public function testWithEnvironmentVariables(): void
    {
        putenv('TEST_VAR=success');

        $input = 'This is a ${TEST_VAR} test';
        $expected = 'This is a success test';
        $result = ($this->transformer)($input);

        $this->assertEquals($expected, $result);
    }

    public function testWithMultipleEnvironmentVariables(): void
    {
        putenv('FIRST_VAR=first');
        putenv('SECOND_VAR=second');

        $input = 'This is ${FIRST_VAR} and ${SECOND_VAR}';
        $expected = 'This is first and second';
        $result = ($this->transformer)($input);

        $this->assertEquals($expected, $result);
    }

    public function testWithPartialEnvironmentVariables(): void
    {
        putenv('PARTIAL_VAR=partial');

        $input = 'This is ${PARTIAL_VAR} and ${UNKNOWN_VAR}';
        $expected = 'This is partial and ${UNKNOWN_VAR}';
        $result = ($this->transformer)($input);

        $this->assertEquals($expected, $result);
    }
}
