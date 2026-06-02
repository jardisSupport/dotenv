<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv\Tests\Unit\Reader;

use JardisSupport\DotEnv\Handler\CastTypeHandler;
use JardisSupport\DotEnv\Exception\EnvFileNotFoundException;
use JardisSupport\DotEnv\Reader\LoadValuesFromFiles;
use PHPUnit\Framework\TestCase;

class LoadValuesFromFilesFileTest extends TestCase
{
    private CastTypeHandler $castTypeHandler;
    private LoadValuesFromFiles $loader;
    private string $fixturesPath;

    protected function setUp(): void
    {
        $this->castTypeHandler = new CastTypeHandler();
        $this->loader = new LoadValuesFromFiles($this->castTypeHandler);
        $this->fixturesPath = dirname(__DIR__, 2) . '/fixtures/file-secrets';
    }

    public function testFileDirectiveResolvesValue(): void
    {
        $files = [$this->fixturesPath . '/.env'];
        $result = ($this->loader)($files, false);

        $this->assertEquals('MyApp', $result['APP_NAME']);
        $this->assertEquals('localhost', $result['DB_HOST']);
        $this->assertEquals('s3cret!Pass', $result['DB_PASSWORD']);
        $this->assertEquals('abc-123-xyz', $result['REDIS_TOKEN']);
        $this->assertFalse($result['DEBUG']);
    }

    public function testFileDirectiveDoesNotExposeFileKey(): void
    {
        $files = [$this->fixturesPath . '/.env'];
        $result = ($this->loader)($files, false);

        $this->assertArrayNotHasKey('DB_PASSWORD_FILE', $result);
        $this->assertArrayNotHasKey('REDIS_TOKEN_FILE', $result);
        $this->assertArrayHasKey('DB_PASSWORD', $result);
        $this->assertArrayHasKey('REDIS_TOKEN', $result);
    }

    public function testFileDirectiveAppliesCastChain(): void
    {
        $files = [$this->fixturesPath . '/.env.cast-test'];
        $result = ($this->loader)($files, false);

        $this->assertSame(8080, $result['API_PORT']);
        $this->assertSame(true, $result['DEBUG_FLAG']);
    }

    public function testFileDirectiveSupportsVariableSubstitution(): void
    {
        $files = [$this->fixturesPath . '/.env.varref'];
        $result = ($this->loader)($files, false);

        $this->assertEquals('mysql://prodhost:s3cret!Pass/mydb', $result['DATABASE_URL']);
    }

    public function testFileDirectiveThrowsOnMissingFile(): void
    {
        $files = [$this->fixturesPath . '/.env.missing'];

        $this->expectException(EnvFileNotFoundException::class);
        ($this->loader)($files, false);
    }

    public function testFileDirectivePublicMode(): void
    {
        // Clear environment
        putenv('DB_PASSWORD');
        putenv('REDIS_TOKEN');
        putenv('DB_HOST');
        putenv('APP_NAME');
        putenv('DEBUG');
        unset($_ENV['DB_PASSWORD'], $_SERVER['DB_PASSWORD']);
        unset($_ENV['REDIS_TOKEN'], $_SERVER['REDIS_TOKEN']);
        unset($_ENV['DB_HOST'], $_SERVER['DB_HOST']);

        $files = [$this->fixturesPath . '/.env'];
        ($this->loader)($files, true);

        $this->assertEquals('s3cret!Pass', $_ENV['DB_PASSWORD']);
        $this->assertEquals('s3cret!Pass', $_SERVER['DB_PASSWORD']);
        $this->assertEquals('s3cret!Pass', getenv('DB_PASSWORD'));
        $this->assertArrayNotHasKey('DB_PASSWORD_FILE', $_ENV);
    }

    public function testFileDirectiveRegistersInVariableRegistry(): void
    {
        $files = [$this->fixturesPath . '/.env'];
        ($this->loader)($files, false);

        $registry = $this->castTypeHandler->getRegistry();
        $this->assertEquals('s3cret!Pass', $registry->get('DB_PASSWORD'));
    }
}
