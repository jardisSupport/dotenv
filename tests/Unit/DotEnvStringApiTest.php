<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv\Tests\Unit;

use InvalidArgumentException;
use JardisSupport\DotEnv\DotEnv;
use JardisSupport\DotEnv\Exception\IncludeNotSupportedException;
use PHPUnit\Framework\TestCase;

class DotEnvStringApiTest extends TestCase
{
    private string $fixturesPath;

    protected function setUp(): void
    {
        $this->fixturesPath = dirname(__DIR__) . '/fixtures';
    }

    public function testLoadPrivateFromStringReturnsCastValues(): void
    {
        $dotEnv = new DotEnv();

        $result = $dotEnv->loadPrivateFromString("APP_NAME=Widget\nDEBUG=true\n");

        $this->assertSame('Widget', $result['APP_NAME']);
        $this->assertTrue($result['DEBUG']);
    }

    public function testLoadPublicFromStringPublishesToSuperglobals(): void
    {
        putenv('DOTENV_STRING_VAR');
        unset($_ENV['DOTENV_STRING_VAR'], $_SERVER['DOTENV_STRING_VAR']);

        $dotEnv = new DotEnv();
        $dotEnv->loadPublicFromString("DOTENV_STRING_VAR=hello\n");

        $this->assertSame('hello', getenv('DOTENV_STRING_VAR'));
        $this->assertSame('hello', $_ENV['DOTENV_STRING_VAR']);
    }

    public function testAddRawKeysAlsoAppliesToStringLoading(): void
    {
        $dotEnv = new DotEnv();
        $dotEnv->addRawKeys(['DB_PASSWORD']);

        $result = $dotEnv->loadPrivateFromString("DB_PASSWORD=false\n");

        $this->assertSame('false', $result['DB_PASSWORD']);
    }

    public function testLoadDirectiveInStringThrows(): void
    {
        $dotEnv = new DotEnv();

        $this->expectException(IncludeNotSupportedException::class);

        $dotEnv->loadPrivateFromString("load(.env.database)\n");
    }

    public function testRelativeKeyFileWithoutBaseDirThrows(): void
    {
        $dotEnv = new DotEnv();

        $this->expectException(InvalidArgumentException::class);

        $dotEnv->loadPrivateFromString("DB_PASSWORD_FILE=secrets/db_password\n");
    }

    public function testRelativeKeyFileWithBaseDirResolves(): void
    {
        $dotEnv = new DotEnv();
        $baseDir = $this->fixturesPath . '/raw-keys/file-secret';

        $result = $dotEnv->loadPrivateFromString("DB_PASSWORD_FILE=secrets/db_password\n", $baseDir);

        $this->assertFalse($result['DB_PASSWORD']);
    }
}
