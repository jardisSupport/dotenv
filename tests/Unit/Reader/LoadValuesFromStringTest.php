<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv\Tests\Unit\Reader;

use InvalidArgumentException;
use JardisSupport\DotEnv\Handler\CastTypeHandler;
use JardisSupport\DotEnv\Handler\MatchesRawKey;
use JardisSupport\DotEnv\Exception\EnvFileNotFoundException;
use JardisSupport\DotEnv\Exception\IncludeNotSupportedException;
use JardisSupport\DotEnv\Reader\LoadValuesFromString;
use PHPUnit\Framework\TestCase;

class LoadValuesFromStringTest extends TestCase
{
    private LoadValuesFromString $loader;
    private string $fixturesPath;

    protected function setUp(): void
    {
        $this->loader = new LoadValuesFromString(new CastTypeHandler());
        $this->fixturesPath = dirname(__DIR__, 2) . '/fixtures';
    }

    public function testParsesKeyValuePairsWithCastChain(): void
    {
        $content = "APP_NAME=Widget\nDEBUG=true\nCOUNT=42\n";

        $result = ($this->loader)($content, false);

        $this->assertSame('Widget', $result['APP_NAME']);
        $this->assertTrue($result['DEBUG']);
        $this->assertSame(42, $result['COUNT']);
    }

    public function testResolvesVariableSubstitution(): void
    {
        $content = "DB_HOST=localhost\nDATABASE_URL=mysql://\${DB_HOST}/app\n";

        $result = ($this->loader)($content, false);

        $this->assertSame('mysql://localhost/app', $result['DATABASE_URL']);
    }

    public function testStripsLeadingUtf8Bom(): void
    {
        $bom = "\xEF\xBB\xBF";
        $result = ($this->loader)($bom . "APP_NAME=Widget\n", false);

        $this->assertArrayHasKey('APP_NAME', $result);
        $this->assertSame('Widget', $result['APP_NAME']);
    }

    public function testSkipsExactlyEmptyLinesButKeepsWhitespaceOnlyLineHarmless(): void
    {
        $content = "APP_NAME=Widget\n\n   \nDEBUG=true\n";

        $result = ($this->loader)($content, false);

        $this->assertSame('Widget', $result['APP_NAME']);
        $this->assertTrue($result['DEBUG']);
        $this->assertCount(2, $result);
    }

    public function testTrailingNewlineDoesNotProduceAPhantomEntry(): void
    {
        $result = ($this->loader)("APP_NAME=Widget\n", false);

        $this->assertCount(1, $result);
    }

    public function testHandlesCrlfLineEndings(): void
    {
        $content = "APP_NAME=Widget\r\nDEBUG=true\r\n";

        $result = ($this->loader)($content, false);

        $this->assertSame('Widget', $result['APP_NAME']);
        $this->assertTrue($result['DEBUG']);
    }

    public function testAbsoluteKeyFileIsResolvedWithoutBaseDir(): void
    {
        $secretFile = $this->fixturesPath . '/raw-keys/file-secret/secrets/db_password';
        $content = 'DB_PASSWORD_FILE=' . $secretFile . "\n";

        $result = ($this->loader)($content, false);

        // No raw key registered here -> the cast chain applies normally to the file content.
        $this->assertFalse($result['DB_PASSWORD']);
        $this->assertArrayNotHasKey('DB_PASSWORD_FILE', $result);
    }

    public function testRelativeKeyFileWithoutBaseDirThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ($this->loader)("DB_PASSWORD_FILE=secrets/db_password\n", false);
    }

    public function testRelativeKeyFileWithBaseDirIsResolved(): void
    {
        $baseDir = $this->fixturesPath . '/raw-keys/file-secret';

        $result = ($this->loader)("DB_PASSWORD_FILE=secrets/db_password\n", false, $baseDir);

        $this->assertFalse($result['DB_PASSWORD']);
    }

    public function testMissingAbsoluteKeyFileThrows(): void
    {
        $this->expectException(EnvFileNotFoundException::class);

        ($this->loader)("DB_PASSWORD_FILE=/does/not/exist\n", false);
    }

    public function testLoadDirectiveIsNotSupported(): void
    {
        try {
            ($this->loader)("load(.env.database)\n", false);
            $this->fail('Expected IncludeNotSupportedException was not thrown');
        } catch (IncludeNotSupportedException $exception) {
            $this->assertSame('.env.database', $exception->getPath());
        }
    }

    public function testOptionalLoadDirectiveIsAlsoNotSupported(): void
    {
        $this->expectException(IncludeNotSupportedException::class);

        ($this->loader)("load?(.env.local)\n", false);
    }

    public function testRawKeyIsExemptFromCasting(): void
    {
        $matchesRawKey = new MatchesRawKey();
        $matchesRawKey->addRawKeys(['_PASSWORD']);
        $loader = new LoadValuesFromString(new CastTypeHandler(), $matchesRawKey);

        $result = ($loader)("DB_PASSWORD=false\n", false);

        $this->assertSame('false', $result['DB_PASSWORD']);
    }

    public function testPublicModePublishesToSuperglobals(): void
    {
        putenv('STRING_INPUT_VAR');
        unset($_ENV['STRING_INPUT_VAR'], $_SERVER['STRING_INPUT_VAR']);

        ($this->loader)("STRING_INPUT_VAR=hello\n", true);

        $this->assertSame('hello', getenv('STRING_INPUT_VAR'));
        $this->assertSame('hello', $_ENV['STRING_INPUT_VAR']);
        $this->assertSame('hello', $_SERVER['STRING_INPUT_VAR']);
    }
}
