<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv\Tests\Unit;

use JardisSupport\DotEnv\DotEnv;
use PHPUnit\Framework\TestCase;

class DotEnvTest extends TestCase
{
    private DotEnv $env;
    private string $fixturesPath;

    protected function setUp(): void
    {
        $this->env = new DotEnv();
        $this->fixturesPath = dirname(__DIR__) . '/fixtures';
        $_ENV['APP_ENV'] = 'test';
        putenv('HOME=/home/user');
    }

    public function testLoadValueProdSuccessful(): void
    {
        $_ENV['APP_ENV'] = 'prod';
        $this->env->loadPublic($this->fixturesPath);
        $this->assertEquals('prodHost', $_ENV['DB_HOST']);
        $this->assertEquals('prodName', $_ENV['DB_NAME']);
        $this->assertEquals('mysql://prodHost:prodName@localhost', $_ENV['DATABASE_URL']);
    }

    public function testLoadValueDevSuccessful(): void
    {
        $_ENV['APP_ENV'] = 'dev';
        $this->env->loadPublic($this->fixturesPath);
        $this->assertEquals('devHost', $_ENV['DB_HOST']);
        $this->assertEquals('devName', $_ENV['DB_NAME']);
        $this->assertEquals('mysql://devHost:devName@localhost', $_ENV['DATABASE_URL']);
    }

    public function testLoadValueTestSuccessful(): void
    {
        $this->env->loadPublic($this->fixturesPath);
        $this->assertEquals('testHost', $_ENV['DB_HOST']);
        $this->assertEquals('testName', $_ENV['DB_NAME']);
        $this->assertEquals('mysql://testHost:testName@localhost', $_ENV['DATABASE_URL']);
    }

    public function testLoadPrivateLoadsCorrectly(): void
    {
        $result = $this->env->loadPrivate($this->fixturesPath);
        $this->assertArrayHasKey('DB_HOST', $result);
        $this->assertEquals('testHost', $result['DB_HOST']);
    }

    public function testLoadPrivateHandlesNonExistentPath(): void
    {
        $result = $this->env->loadPrivate('/path/to/nonexistent');
        $this->assertEmpty($result);
    }

    public function testEnvVarType(): void
    {
        $_ENV['APP_ENV'] = 'dev';
        $this->env->loadPublic($this->fixturesPath);
        $this->assertEquals(2, $_ENV['INT_VAR']);
        $this->assertEquals(false, $_ENV['BOOL_VAR']);
    }

    public function testPrivateEnvVarType(): void
    {
        $result = $this->env->loadPrivate($this->fixturesPath);
        $this->assertEquals(3, $result['INT_VAR']);
        $this->assertEquals(false, $result['BOOL_VAR']);
    }

    public function testPrivateEnvVarArrayType(): void
    {
        $result = $this->env->loadPrivate($this->fixturesPath);
        $this->assertIsArray($result['TEST']);
        $this->assertIsBool($result['TEST']['b']);
        $this->assertIsFloat($result['TEST'][1]);
        $this->assertIsString($result['TEST'][3]);
        $this->assertIsArray($result['TEST']['test']);
        $this->assertIsArray($result['TEST']['test']['test2']);
        $this->assertCount(4, $result['TEST']['test']['test2']);
    }

    public function testPrivateEnvVarHOME(): void
    {
        $result = $this->env->loadPrivate($this->fixturesPath);
        $this->assertIsString($result['HOME']);
        $this->assertStringContainsString('/', $result['HOME']);
    }

    public function testPrivateVariableSubstitutionWorks(): void
    {
        putenv('DB_HOST');
        putenv('DB_NAME');

        $result = $this->env->loadPrivate($this->fixturesPath);

        $this->assertStringNotContainsString('${DB_HOST}', $result['DATABASE_URL']);
        $this->assertStringNotContainsString('${DB_NAME}', $result['DATABASE_URL']);
        $this->assertStringContainsString($result['DB_HOST'], $result['DATABASE_URL']);
    }

    public function testTildeExpansionWithCustomHomePublic(): void
    {
        unset($_ENV['APP_ENV']);
        putenv('APP_ENV');

        $homePath = dirname(__DIR__) . '/fixtures/home-override';
        $this->env->loadPublic($homePath);

        $this->assertEquals('/custom/home', $_ENV['HOME']);
        $this->assertEquals('/custom/home/logs', $_ENV['LOG_DIR']);
    }

    public function testTildeExpansionWithCustomHomePrivate(): void
    {
        unset($_ENV['APP_ENV']);
        putenv('APP_ENV');

        $homePath = dirname(__DIR__) . '/fixtures/home-override';
        $result = $this->env->loadPrivate($homePath);

        $this->assertEquals('/custom/home', $result['HOME']);
        $this->assertEquals('/custom/home/logs', $result['LOG_DIR']);
    }

    public function testAppEnvBootstrapFromDotEnvPublic(): void
    {
        unset($_ENV['APP_ENV']);
        putenv('APP_ENV');

        $appEnvPath = dirname(__DIR__) . '/fixtures/appenv';
        $this->env->loadPublic($appEnvPath);

        $this->assertEquals('staging', $_ENV['APP_ENV']);
        $this->assertEquals('from_staging', $_ENV['STAGING_VAR']);
        $this->assertEquals('overridden_by_staging', $_ENV['BASE_VAR']);
    }

    public function testAppEnvBootstrapFromDotEnvPrivate(): void
    {
        unset($_ENV['APP_ENV']);
        putenv('APP_ENV');

        $appEnvPath = dirname(__DIR__) . '/fixtures/appenv';
        $result = $this->env->loadPrivate($appEnvPath);

        $this->assertEquals('staging', $result['APP_ENV']);
        $this->assertEquals('from_staging', $result['STAGING_VAR']);
        $this->assertEquals('overridden_by_staging', $result['BASE_VAR']);
    }

    public function testRemoveHandlerDisablesCaster(): void
    {
        $this->env->removeHandler(\JardisSupport\DotEnv\Handler\CastStringToBool::class);

        unset($_ENV['APP_ENV']);
        putenv('APP_ENV');

        $result = $this->env->loadPrivate($this->fixturesPath);

        $this->assertSame('true', $result['BOOL_VAR']);
    }

    public function testAddHandlerPrependRunsFirst(): void
    {
        $handler = new class {
            public function __invoke(?string $value = null): ?string
            {
                if ($value === 'prodHost') {
                    return 'INTERCEPTED';
                }
                return $value;
            }
        };

        $this->env->addHandler($handler, prepend: true);

        unset($_ENV['APP_ENV']);
        putenv('APP_ENV');

        $result = $this->env->loadPrivate($this->fixturesPath);

        $this->assertEquals('INTERCEPTED', $result['DB_HOST']);
    }
}
