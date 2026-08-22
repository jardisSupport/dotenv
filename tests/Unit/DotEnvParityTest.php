<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv\Tests\Unit;

use JardisSupport\DotEnv\DotEnv;
use PHPUnit\Framework\TestCase;

/**
 * AK1.3: file-based and string-based loading must produce identical result arrays for the same
 * content — casts, ${VAR} substitution, an absolute KEY_FILE, CRLF line endings, a trailing
 * newline and a whitespace-only line. A leading UTF-8 BOM is string-input-only (a file never
 * carries one in these fixtures) and is asserted separately.
 */
class DotEnvParityTest extends TestCase
{
    private string $tempDir;
    private string $content;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/dotenv-parity-' . uniqid();
        mkdir($this->tempDir);

        $secretFile = dirname(__DIR__) . '/fixtures/raw-keys/file-secret/secrets/db_password';

        // Mixed LF/CRLF, a trailing newline and a whitespace-only line in between.
        $this->content = "APP_NAME=Widget\r\n"
            . "DB_HOST=localhost\n"
            . "DATABASE_URL=mysql://\${DB_HOST}/app\n"
            . "   \n"
            . "DEBUG=true\n"
            . "COUNT=42\n"
            . 'DB_PASSWORD_FILE=' . $secretFile . "\n";

        file_put_contents($this->tempDir . '/.env', $this->content);
    }

    protected function tearDown(): void
    {
        unlink($this->tempDir . '/.env');
        rmdir($this->tempDir);
    }

    public function testFileAndStringLoadingProduceIdenticalResults(): void
    {
        $fromFile = (new DotEnv())->loadPrivate($this->tempDir);
        $fromString = (new DotEnv())->loadPrivateFromString($this->content);

        $this->assertSame($fromFile, $fromString);

        // Spot-check the interesting values so a broken fixture fails loudly, not silently.
        $this->assertSame('Widget', $fromFile['APP_NAME']);
        $this->assertSame('mysql://localhost/app', $fromFile['DATABASE_URL']);
        $this->assertTrue($fromFile['DEBUG']);
        $this->assertSame(42, $fromFile['COUNT']);
        $this->assertFalse($fromFile['DB_PASSWORD']);
        $this->assertArrayNotHasKey('DB_PASSWORD_FILE', $fromFile);
    }

    public function testLeadingBomIsStringOnlyAndHasNoEffectOnTheParsedResult(): void
    {
        $bom = "\xEF\xBB\xBF";

        $withoutBom = (new DotEnv())->loadPrivateFromString($this->content);
        $withBom = (new DotEnv())->loadPrivateFromString($bom . $this->content);

        $this->assertSame($withoutBom, $withBom);
    }
}
