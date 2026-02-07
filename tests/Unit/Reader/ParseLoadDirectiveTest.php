<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv\Tests\Unit\Reader;

use JardisSupport\DotEnv\Reader\ParseLoadDirective;
use PHPUnit\Framework\TestCase;

class ParseLoadDirectiveTest extends TestCase
{
    private ParseLoadDirective $parser;

    protected function setUp(): void
    {
        $this->parser = new ParseLoadDirective();
    }

    public function testParseRequiredLoadDirective(): void
    {
        $result = ($this->parser)('load(.env.database)');

        $this->assertNotNull($result);
        $this->assertEquals('.env.database', $result['path']);
        $this->assertFalse($result['optional']);
    }

    public function testParseOptionalLoadDirective(): void
    {
        $result = ($this->parser)('load?(.env.local)');

        $this->assertNotNull($result);
        $this->assertEquals('.env.local', $result['path']);
        $this->assertTrue($result['optional']);
    }

    public function testParseDoubleQuotedPath(): void
    {
        $result = ($this->parser)('load("path with spaces/.env")');

        $this->assertNotNull($result);
        $this->assertEquals('path with spaces/.env', $result['path']);
        $this->assertFalse($result['optional']);
    }

    public function testParseSingleQuotedPath(): void
    {
        $result = ($this->parser)("load?('./relative/path/.env')");

        $this->assertNotNull($result);
        $this->assertEquals('./relative/path/.env', $result['path']);
        $this->assertTrue($result['optional']);
    }

    public function testParseWithLeadingWhitespace(): void
    {
        $result = ($this->parser)('   load(.env.test)');

        $this->assertNotNull($result);
        $this->assertEquals('.env.test', $result['path']);
    }

    public function testParseWithTrailingWhitespace(): void
    {
        $result = ($this->parser)('load(.env.test)   ');

        $this->assertNotNull($result);
        $this->assertEquals('.env.test', $result['path']);
    }

    public function testReturnsNullForNonLoadDirective(): void
    {
        $result = ($this->parser)('DB_HOST=localhost');

        $this->assertNull($result);
    }

    public function testReturnsNullForComment(): void
    {
        $result = ($this->parser)('# load(.env.database)');

        $this->assertNull($result);
    }

    public function testReturnsNullForEmptyLine(): void
    {
        $result = ($this->parser)('');

        $this->assertNull($result);
    }

    public function testReturnsNullForMissingParentheses(): void
    {
        $result = ($this->parser)('load .env.database');

        $this->assertNull($result);
    }

    public function testReturnsNullForEmptyPath(): void
    {
        $result = ($this->parser)('load()');

        $this->assertNull($result);
    }

    public function testReturnsNullForUppercaseLoad(): void
    {
        $result = ($this->parser)('LOAD(.env.database)');

        $this->assertNull($result);
    }

    public function testReturnsNullForMixedCaseLoad(): void
    {
        $result = ($this->parser)('Load(.env.database)');

        $this->assertNull($result);
    }

    public function testParseAbsolutePath(): void
    {
        $result = ($this->parser)('load(/absolute/path/.env)');

        $this->assertNotNull($result);
        $this->assertEquals('/absolute/path/.env', $result['path']);
    }

    public function testParseRelativePathWithDots(): void
    {
        $result = ($this->parser)('load(../parent/.env)');

        $this->assertNotNull($result);
        $this->assertEquals('../parent/.env', $result['path']);
    }
}
