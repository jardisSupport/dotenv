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
    private string $secretsPath;

    /** @var array<string> Temp directories created by writeTempEnvFile(), removed in tearDown. */
    private array $tempDirs = [];

    protected function setUp(): void
    {
        $this->castTypeHandler = new CastTypeHandler();
        $this->loader = new LoadValuesFromFiles($this->castTypeHandler);
        $this->fixturesPath = dirname(__DIR__, 2) . '/fixtures/file-secrets';
        $this->secretsPath = $this->fixturesPath . '/secrets';
    }

    protected function tearDown(): void
    {
        foreach ($this->tempDirs as $tempDir) {
            $entries = glob($tempDir . '/*') ?: [];
            foreach ($entries as $entry) {
                @unlink($entry);
            }
            @rmdir($tempDir);
        }
    }

    /**
     * behaviour changed 2026-08-30: _FILE resolution requires an absolute path (Bescheid Rolf,
     * Gabel G3 env-kollisionen). These fixtures used to reference secrets/* by a relative path
     * that the file's own directory resolved implicitly; that no longer resolves, so each test
     * now writes a temp .env pointing at the same pre-existing secret files by absolute path.
     */
    private function writeTempEnvFile(string $content): string
    {
        $tempDir = sys_get_temp_dir() . '/dotenv-file-secrets-' . uniqid();
        mkdir($tempDir);
        file_put_contents($tempDir . '/.env', $content);
        $this->tempDirs[] = $tempDir;

        return $tempDir . '/.env';
    }

    public function testFileDirectiveResolvesValue(): void
    {
        $envFile = $this->writeTempEnvFile(
            "APP_NAME=MyApp\n"
            . "DB_HOST=localhost\n"
            . 'DB_PASSWORD_FILE=' . $this->secretsPath . "/db_password\n"
            . 'REDIS_TOKEN_FILE=' . $this->secretsPath . "/redis_token\n"
            . "DEBUG=false\n"
        );

        $result = ($this->loader)([$envFile], false);

        $this->assertEquals('MyApp', $result['APP_NAME']);
        $this->assertEquals('localhost', $result['DB_HOST']);
        $this->assertEquals('s3cret!Pass', $result['DB_PASSWORD']);
        $this->assertEquals('abc-123-xyz', $result['REDIS_TOKEN']);
        $this->assertFalse($result['DEBUG']);
    }

    public function testFileDirectiveDoesNotExposeFileKey(): void
    {
        $envFile = $this->writeTempEnvFile(
            'DB_PASSWORD_FILE=' . $this->secretsPath . "/db_password\n"
            . 'REDIS_TOKEN_FILE=' . $this->secretsPath . "/redis_token\n"
        );

        $result = ($this->loader)([$envFile], false);

        $this->assertArrayNotHasKey('DB_PASSWORD_FILE', $result);
        $this->assertArrayNotHasKey('REDIS_TOKEN_FILE', $result);
        $this->assertArrayHasKey('DB_PASSWORD', $result);
        $this->assertArrayHasKey('REDIS_TOKEN', $result);
    }

    public function testFileDirectiveAppliesCastChain(): void
    {
        $envFile = $this->writeTempEnvFile(
            'API_PORT_FILE=' . $this->secretsPath . "/api_port\n"
            . 'DEBUG_FLAG_FILE=' . $this->secretsPath . "/debug_flag\n"
        );

        $result = ($this->loader)([$envFile], false);

        $this->assertSame(8080, $result['API_PORT']);
        $this->assertSame(true, $result['DEBUG_FLAG']);
    }

    public function testFileDirectiveSupportsVariableSubstitution(): void
    {
        $envFile = $this->writeTempEnvFile(
            "DB_HOST=prodhost\n"
            . 'DB_PASSWORD_FILE=' . $this->secretsPath . "/db_password\n"
            . 'DATABASE_URL_FILE=' . $this->secretsPath . "/var_ref\n"
        );

        $result = ($this->loader)([$envFile], false);

        $this->assertEquals('mysql://prodhost:s3cret!Pass/mydb', $result['DATABASE_URL']);
    }

    public function testFileDirectiveThrowsOnMissingFile(): void
    {
        // Unaffected by the absolute-only change: the secret path here was already absolute.
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

        $envFile = $this->writeTempEnvFile(
            "APP_NAME=MyApp\n"
            . "DB_HOST=localhost\n"
            . 'DB_PASSWORD_FILE=' . $this->secretsPath . "/db_password\n"
            . 'REDIS_TOKEN_FILE=' . $this->secretsPath . "/redis_token\n"
            . "DEBUG=false\n"
        );

        ($this->loader)([$envFile], true);

        $this->assertEquals('s3cret!Pass', $_ENV['DB_PASSWORD']);
        $this->assertEquals('s3cret!Pass', $_SERVER['DB_PASSWORD']);
        $this->assertEquals('s3cret!Pass', getenv('DB_PASSWORD'));
        $this->assertArrayNotHasKey('DB_PASSWORD_FILE', $_ENV);
    }

    public function testFileDirectiveRegistersInVariableRegistry(): void
    {
        $envFile = $this->writeTempEnvFile(
            'DB_PASSWORD_FILE=' . $this->secretsPath . "/db_password\n"
        );

        ($this->loader)([$envFile], false);

        $registry = $this->castTypeHandler->getRegistry();
        $this->assertEquals('s3cret!Pass', $registry->get('DB_PASSWORD'));
    }
}
