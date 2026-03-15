<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv\Tests\Unit\Exception;

use JardisSupport\DotEnv\Exception\CircularEnvIncludeException;
use PHPUnit\Framework\TestCase;

class CircularEnvIncludeExceptionTest extends TestCase
{
    public function testExceptionMessage(): void
    {
        $stack = ['/path/to/.env', '/path/to/.env.a'];
        $exception = new CircularEnvIncludeException('/path/to/.env', $stack);

        $this->assertStringContainsString('Circular include detected', $exception->getMessage());
        $this->assertStringContainsString('/path/to/.env', $exception->getMessage());
        $this->assertStringContainsString('/path/to/.env.a', $exception->getMessage());
    }

    public function testGetIncludeStack(): void
    {
        $stack = ['/path/to/.env', '/path/to/.env.a', '/path/to/.env.b'];
        $exception = new CircularEnvIncludeException('/path/to/.env', $stack);

        $this->assertEquals($stack, $exception->getIncludeStack());
    }
}
