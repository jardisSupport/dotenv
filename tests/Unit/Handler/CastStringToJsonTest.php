<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv\Tests\Unit\Handler;

use JardisSupport\DotEnv\Handler\CastStringToJson;
use JardisSupport\DotEnv\Handler\CastTypeHandler;
use PHPUnit\Framework\TestCase;

class CastStringToJsonTest extends TestCase
{
    private CastStringToJson $castStringToJson;
    private CastTypeHandler $castTypeHandler;

    protected function setUp(): void
    {
        $this->castTypeHandler = new CastTypeHandler();
        $this->castStringToJson = new CastStringToJson($this->castTypeHandler);
    }

    public function testWithNull(): void
    {
        $result = ($this->castStringToJson)(null);
        $this->assertNull($result);
    }

    public function testWithSimpleString(): void
    {
        $result = ($this->castStringToJson)('hello world');
        $this->assertSame('hello world', $result);
    }

    public function testWithJsonObject(): void
    {
        $input = '{"host":"localhost","port":3306}';
        $result = ($this->castStringToJson)($input);

        $expected = [
            'host' => 'localhost',
            'port' => 3306,
        ];

        $this->assertSame($expected, $result);
    }

    public function testWithJsonArray(): void
    {
        $input = '["alpha","beta","gamma"]';
        $result = ($this->castStringToJson)($input);

        $expected = ['alpha', 'beta', 'gamma'];

        $this->assertSame($expected, $result);
    }

    public function testWithNestedJson(): void
    {
        $input = '{"db":{"host":"localhost","port":3306},"debug":true}';
        $result = ($this->castStringToJson)($input);

        $expected = [
            'db' => [
                'host' => 'localhost',
                'port' => 3306,
            ],
            'debug' => true,
        ];

        $this->assertSame($expected, $result);
    }

    public function testWithMixedTypesInJsonArray(): void
    {
        $input = '[1,"two",true,null,4.5]';
        $result = ($this->castStringToJson)($input);

        $expected = [1, 'two', true, null, 4.5];

        $this->assertSame($expected, $result);
    }

    public function testCustomArrayFormatIsNotJson(): void
    {
        $input = '[a=>1,b=>2]';
        $result = ($this->castStringToJson)($input);

        // Not valid JSON, returned as-is
        $this->assertSame($input, $result);
    }

    public function testInvalidJsonReturnsString(): void
    {
        $input = '{invalid json}';
        $result = ($this->castStringToJson)($input);

        $this->assertSame($input, $result);
    }

    public function testStringValuesAreCastByHandler(): void
    {
        putenv('JSON_TEST_VAR=resolved');

        $input = '{"path":"${JSON_TEST_VAR}"}';
        $result = ($this->castStringToJson)($input);

        $this->assertIsArray($result);
        $this->assertSame('resolved', $result['path']);

        putenv('JSON_TEST_VAR');
    }

    public function testJsonWithBooleanValues(): void
    {
        $input = '{"enabled":true,"disabled":false}';
        $result = ($this->castStringToJson)($input);

        $expected = [
            'enabled' => true,
            'disabled' => false,
        ];

        $this->assertSame($expected, $result);
    }

    public function testEmptyJsonObject(): void
    {
        $input = '{}';
        $result = ($this->castStringToJson)($input);

        $this->assertSame([], $result);
    }

    public function testEmptyJsonArray(): void
    {
        $input = '[]';
        $result = ($this->castStringToJson)($input);

        $this->assertSame([], $result);
    }
}
