<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv\Tests\Unit\Exception;

use JardisSupport\DotEnv\Exception\EnvFileNotFoundException;
use PHPUnit\Framework\TestCase;

class EnvFileNotFoundExceptionTest extends TestCase
{
    public function testExceptionMessage(): void
    {
        $exception = new EnvFileNotFoundException('/path/to/.env.missing');

        $this->assertStringContainsString('Environment file not found', $exception->getMessage());
        $this->assertStringContainsString('/path/to/.env.missing', $exception->getMessage());
    }

    public function testGetFilePath(): void
    {
        $filePath = '/path/to/.env.missing';
        $exception = new EnvFileNotFoundException($filePath);

        $this->assertEquals($filePath, $exception->getFilePath());
    }
}
