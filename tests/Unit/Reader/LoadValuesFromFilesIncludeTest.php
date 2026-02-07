<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv\Tests\Unit\Reader;

use JardisSupport\DotEnv\Casting\CastTypeHandler;
use JardisSupport\DotEnv\Exception\CircularEnvIncludeException;
use JardisSupport\DotEnv\Exception\EnvFileNotFoundException;
use JardisSupport\DotEnv\Reader\LoadValuesFromFiles;
use PHPUnit\Framework\TestCase;

class LoadValuesFromFilesIncludeTest extends TestCase
{
    private CastTypeHandler $castTypeHandler;
    private LoadValuesFromFiles $loader;
    private string $fixturesPath;

    protected function setUp(): void
    {
        $this->castTypeHandler = new CastTypeHandler();
        $this->loader = new LoadValuesFromFiles($this->castTypeHandler);
        $this->fixturesPath = dirname(__DIR__, 2) . '/fixtures/include';
    }

    public function testBasicIncludeLoadsFile(): void
    {
        $files = [$this->fixturesPath . '/.env'];
        $result = ($this->loader)($files, false);

        // Variables from main file
        $this->assertArrayHasKey('APP_NAME', $result);
        $this->assertEquals('TestApp', $result['APP_NAME']);

        // Variables from included .env.database
        $this->assertArrayHasKey('DB_HOST', $result);
        $this->assertEquals('localhost', $result['DB_HOST']);

        // Variables from included .env.logger
        $this->assertArrayHasKey('LOG_LEVEL', $result);
        $this->assertEquals('debug', $result['LOG_LEVEL']);
    }

    public function testOptionalIncludeLoadsExistingFile(): void
    {
        $files = [$this->fixturesPath . '/.env'];
        $result = ($this->loader)($files, false);

        // Variable from optional .env.optional that exists
        $this->assertArrayHasKey('OPTIONAL_VAR', $result);
        $this->assertEquals('exists', $result['OPTIONAL_VAR']);
    }

    public function testOptionalIncludeSkipsMissingFile(): void
    {
        $files = [$this->fixturesPath . '/.env'];
        $result = ($this->loader)($files, false);

        // Should complete without error even though .env.nonexistent doesn't exist
        $this->assertArrayHasKey('APP_NAME', $result);
    }

    public function testRequiredIncludeThrowsExceptionForMissingFile(): void
    {
        // Create a temporary file with a required include for a non-existent file
        $tempFile = $this->fixturesPath . '/.env.temp-required';
        file_put_contents($tempFile, "TEST_VAR=test\nload(.env.does-not-exist)");

        try {
            $this->expectException(EnvFileNotFoundException::class);
            ($this->loader)([$tempFile], false);
        } finally {
            unlink($tempFile);
        }
    }

    public function testOverrideBehavior(): void
    {
        $files = [$this->fixturesPath . '/.env.override-test'];
        $result = ($this->loader)($files, false);

        // The included file should override the original value
        $this->assertEquals('overridden', $result['OVERRIDE_VAR']);

        // Variable defined after include should be present
        $this->assertEquals('after', $result['AFTER_INCLUDE']);

        // Variable from included file
        $this->assertArrayHasKey('INCLUDED_VAR', $result);
    }

    public function testNestedIncludes(): void
    {
        $files = [$this->fixturesPath . '/.env.chain-a'];
        $result = ($this->loader)($files, false);

        // Should have variables from A -> B -> C chain
        $this->assertEquals('valueA', $result['CHAIN_A']);
        $this->assertEquals('valueB', $result['CHAIN_B']);
        $this->assertEquals('valueC', $result['CHAIN_C']);
    }

    public function testCircularReferenceDetectionDirect(): void
    {
        $files = [$this->fixturesPath . '/.env.self-circular'];

        $this->expectException(CircularEnvIncludeException::class);
        ($this->loader)($files, false);
    }

    public function testCircularReferenceDetectionIndirect(): void
    {
        $files = [$this->fixturesPath . '/.env.circular-a'];

        $this->expectException(CircularEnvIncludeException::class);
        ($this->loader)($files, false);
    }

    public function testRelativePathResolution(): void
    {
        $files = [$this->fixturesPath . '/.env.nested-loader'];
        $result = ($this->loader)($files, false);

        // Variable from main file
        $this->assertEquals('loader', $result['LOADER_VAR']);

        // Variable from nested/.env.nested
        $this->assertEquals('nested_value', $result['NESTED_VAR']);
    }

    public function testMultipleIncludes(): void
    {
        $files = [$this->fixturesPath . '/.env'];
        $result = ($this->loader)($files, false);

        // Variables from multiple includes should all be present
        $this->assertArrayHasKey('DB_HOST', $result);
        $this->assertArrayHasKey('LOG_LEVEL', $result);
        $this->assertArrayHasKey('OPTIONAL_VAR', $result);
    }

    public function testMixedContentWithIncludes(): void
    {
        $files = [$this->fixturesPath . '/.env'];
        $result = ($this->loader)($files, false);

        // Variables before includes
        $this->assertEquals('TestApp', $result['APP_NAME']);

        // Variables after includes
        $this->assertEquals(true, $result['APP_DEBUG']);
    }

    public function testPublicModeWithIncludes(): void
    {
        // Clear environment first
        putenv('DB_HOST');
        unset($_ENV['DB_HOST'], $_SERVER['DB_HOST']);

        $files = [$this->fixturesPath . '/.env'];
        ($this->loader)($files, true);

        // Check that included values are in environment
        $this->assertEquals('localhost', getenv('DB_HOST'));
        $this->assertEquals('localhost', $_ENV['DB_HOST']);
    }

    public function testExceptionContainsIncludeStack(): void
    {
        $files = [$this->fixturesPath . '/.env.circular-a'];

        try {
            ($this->loader)($files, false);
            $this->fail('Expected CircularEnvIncludeException was not thrown');
        } catch (CircularEnvIncludeException $e) {
            $stack = $e->getIncludeStack();
            $this->assertNotEmpty($stack);
            $this->assertStringContainsString('Circular include detected', $e->getMessage());
        }
    }

    // --- Cascade Tests ---

    public function testIncludeCascadeLoadsLocalVariant(): void
    {
        $savedAppEnv = $_ENV['APP_ENV'] ?? null;
        unset($_ENV['APP_ENV']);

        try {
            $files = [$this->fixturesPath . '/cascade/.env'];
            $result = ($this->loader)($files, false);

            // Base value overridden by .local
            $this->assertEquals('local_override', $result['BASE_VAR']);
            // Value only in .local
            $this->assertArrayHasKey('LOCAL_SECRET', $result);
            $this->assertEquals('my_secret', $result['LOCAL_SECRET']);
            // Base value not overridden
            $this->assertEquals('localhost', $result['SERVICE_HOST']);
        } finally {
            if ($savedAppEnv !== null) {
                $_ENV['APP_ENV'] = $savedAppEnv;
            }
        }
    }

    public function testIncludeCascadeLoadsAppEnvVariant(): void
    {
        $savedAppEnv = $_ENV['APP_ENV'] ?? null;
        $_ENV['APP_ENV'] = 'dev';

        try {
            $files = [$this->fixturesPath . '/cascade/.env'];
            $result = ($this->loader)($files, false);

            // DEV_VAR from .dev overridden by .dev.local
            $this->assertEquals('dev_local_override', $result['DEV_VAR']);
            // SERVICE_HOST overridden by .dev
            $this->assertEquals('dev-server', $result['SERVICE_HOST']);
            // DEV_LOCAL_SECRET from .dev.local
            $this->assertArrayHasKey('DEV_LOCAL_SECRET', $result);
            $this->assertEquals('dev_secret', $result['DEV_LOCAL_SECRET']);
        } finally {
            if ($savedAppEnv !== null) {
                $_ENV['APP_ENV'] = $savedAppEnv;
            } else {
                unset($_ENV['APP_ENV']);
            }
        }
    }

    public function testIncludeCascadeOverrideOrder(): void
    {
        $savedAppEnv = $_ENV['APP_ENV'] ?? null;
        $_ENV['APP_ENV'] = 'dev';

        try {
            $files = [$this->fixturesPath . '/cascade/.env'];
            $result = ($this->loader)($files, false);

            // BASE_VAR: base → local_override (from .local) → not in .dev → not in .dev.local
            $this->assertEquals('local_override', $result['BASE_VAR']);

            // SERVICE_HOST: localhost (base) → not in .local → dev-server (.dev) → not in .dev.local
            $this->assertEquals('dev-server', $result['SERVICE_HOST']);

            // DEV_VAR: not in base → not in .local → dev_value (.dev) → dev_local_override (.dev.local)
            $this->assertEquals('dev_local_override', $result['DEV_VAR']);
        } finally {
            if ($savedAppEnv !== null) {
                $_ENV['APP_ENV'] = $savedAppEnv;
            } else {
                unset($_ENV['APP_ENV']);
            }
        }
    }

    public function testIncludeCascadeBaseOnlyWhenNoVariantsExist(): void
    {
        $savedAppEnv = $_ENV['APP_ENV'] ?? null;
        $_ENV['APP_ENV'] = 'dev';

        try {
            $files = [$this->fixturesPath . '/cascade-base-only/.env'];
            $result = ($this->loader)($files, false);

            // Only base file values present
            $this->assertEquals('standalone_value', $result['STANDALONE_VAR']);
            $this->assertEquals('localhost', $result['STANDALONE_HOST']);
            $this->assertEquals('main', $result['MAIN_VAR']);
        } finally {
            if ($savedAppEnv !== null) {
                $_ENV['APP_ENV'] = $savedAppEnv;
            } else {
                unset($_ENV['APP_ENV']);
            }
        }
    }
}
