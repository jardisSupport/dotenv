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
        $result = ($this->loadFilesFromPath)($this->basePath);

        $expected = [
            $this->basePath . '/.env',
            $this->basePath . '/.env.local',
        ];

        $this->assertEquals($expected, $result);
    }

    public function testWithAppEnv(): void
    {
        $result = ($this->loadFilesFromPath)($this->basePath, 'dev');

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
        $result = ($this->loadFilesFromPath)('', 'dev');

        $this->assertEmpty($result);
    }

    public function testGetBaseFilesReturnsOnlyBaseFiles(): void
    {
        $result = $this->loadFilesFromPath->getBaseFiles($this->basePath);

        $expected = [
            $this->basePath . '/.env',
            $this->basePath . '/.env.local',
        ];

        $this->assertEquals($expected, $result);
    }

    public function testGetEnvFilesReturnsOnlyEnvSpecificFiles(): void
    {
        $result = $this->loadFilesFromPath->getEnvFiles($this->basePath, 'dev');

        $expected = [
            $this->basePath . '/.env.dev',
            $this->basePath . '/.env.dev.local',
        ];

        $this->assertEquals($expected, $result);
    }

    public function testGetEnvFilesWithNonExistentEnv(): void
    {
        $result = $this->loadFilesFromPath->getEnvFiles($this->basePath, 'staging');

        $this->assertEmpty($result);
    }
}
