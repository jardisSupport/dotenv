<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv\Tests\Unit\Reader;

use JardisSupport\DotEnv\Reader\LoadFilesFromPath;
use PHPUnit\Framework\TestCase;

class LoadFilesFromPathTest extends TestCase
{
    private string $basePath = __DIR__ . '/../../fixtures';

    private LoadFilesFromPath $loadFilesFromPath;
    protected function setUp(): void
    {
        $this->loadFilesFromPath = new LoadFilesFromPath();
    }

    public function testWithNullEnvironment(): void
    {
        unset($_ENV['APP_ENV']);
        $result = ($this->loadFilesFromPath)($this->basePath);

        $expected = [
            $this->basePath . '/.env',
            $this->basePath . '/.env.local',
        ];

        $this->assertEquals($expected, $result);
    }

    public function testWithAppEnv(): void
    {
        $_ENV['APP_ENV'] = 'dev';
        $result = ($this->loadFilesFromPath)($this->basePath);

        $expected = [
            $this->basePath . '/.env',
            $this->basePath . '/.env.local',
            $this->basePath . '/.env.dev',
            $this->basePath . '/.env.dev.local',
        ];

        $this->assertEquals($expected, $result);
    }

    public function testWithEmptyPath(): void
    {
        $_ENV['APP_ENV'] = 'dev';

        $result = ($this->loadFilesFromPath)('');

        $this->assertEmpty($result);
    }
}
