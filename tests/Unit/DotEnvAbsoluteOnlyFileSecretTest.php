<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv\Tests\Unit;

use JardisSupport\DotEnv\DotEnv;
use PHPUnit\Framework\TestCase;

/**
 * AK env-kollisionen G3 (Bescheid Rolf 2026-08-30): "_FILE" secret resolution applies to ABSOLUTE
 * paths only. Docker-/Kubernetes-Secrets are always absolute mount paths; stack keys like
 * COMPOSE_FILE or NGINX_INDEX_FILE happen to share the "_FILE" suffix as a plain name, not a
 * secret-mount command, and must survive as ordinary string values with no file lookup, no key
 * rename and no exception.
 */
class DotEnvAbsoluteOnlyFileSecretTest extends TestCase
{
    /** @var array<string> */
    private const TOUCHED_KEYS = ['COMPOSE_FILE', 'COMPOSE', 'NGINX_INDEX_FILE', 'NGINX_INDEX', 'DB_PASSWORD_FILE', 'DB_PASSWORD'];

    protected function setUp(): void
    {
        foreach (self::TOUCHED_KEYS as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }
    }

    protected function tearDown(): void
    {
        foreach (self::TOUCHED_KEYS as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }
    }

    /** 1: a relative COMPOSE_FILE value stays under its own key, no "COMPOSE" key is created. */
    public function testRelativeComposeFileValueStaysAsPlainKey(): void
    {
        $result = (new DotEnv())->loadPrivateFromString("COMPOSE_FILE=support/docker-compose.yml\n");

        $this->assertSame('support/docker-compose.yml', $result['COMPOSE_FILE']);
        $this->assertArrayNotHasKey('COMPOSE', $result);
    }

    /** 2: a relative NGINX_INDEX_FILE value is a plain string, even though no such file exists. */
    public function testRelativeNginxIndexFileValueIsPlainStringWithoutException(): void
    {
        $result = (new DotEnv())->loadPrivateFromString("NGINX_INDEX_FILE=index.php\n");

        $this->assertSame('index.php', $result['NGINX_INDEX_FILE']);
        $this->assertArrayNotHasKey('NGINX_INDEX', $result);
    }

    /** 3: regression — an absolute DB_PASSWORD_FILE path is still read and stripped, as before. */
    public function testAbsoluteFileSecretStillResolvesAndStripsSuffix(): void
    {
        $secretFile = dirname(__DIR__) . '/fixtures/raw-keys/file-secret/secrets/db_password';

        $result = (new DotEnv())->loadPrivateFromString('DB_PASSWORD_FILE=' . $secretFile . "\n");

        $this->assertFalse($result['DB_PASSWORD']);
        $this->assertArrayNotHasKey('DB_PASSWORD_FILE', $result);
    }

    /** 4a: the same three cases hold through loadPublicFromString() — string input shares the chain. */
    public function testRelativeComposeFileValueStaysAsPlainKeyViaLoadPublicFromString(): void
    {
        (new DotEnv())->loadPublicFromString("COMPOSE_FILE=support/docker-compose.yml\n");

        $this->assertSame('support/docker-compose.yml', $_ENV['COMPOSE_FILE']);
        $this->assertArrayNotHasKey('COMPOSE', $_ENV);
    }

    /** 4b: same for the relative NGINX_INDEX_FILE case via loadPublicFromString(). */
    public function testRelativeNginxIndexFileValueIsPlainStringViaLoadPublicFromString(): void
    {
        (new DotEnv())->loadPublicFromString("NGINX_INDEX_FILE=index.php\n");

        $this->assertSame('index.php', $_ENV['NGINX_INDEX_FILE']);
        $this->assertArrayNotHasKey('NGINX_INDEX', $_ENV);
    }

    /** 4c: same regression check (absolute path still resolves) via loadPublicFromString(). */
    public function testAbsoluteFileSecretStillResolvesViaLoadPublicFromString(): void
    {
        $secretFile = dirname(__DIR__) . '/fixtures/raw-keys/file-secret/secrets/db_password';

        (new DotEnv())->loadPublicFromString('DB_PASSWORD_FILE=' . $secretFile . "\n");

        $this->assertFalse($_ENV['DB_PASSWORD']);
        $this->assertArrayNotHasKey('DB_PASSWORD_FILE', $_ENV);
    }

    /** 5: sources() reports the relative-case key as COMPOSE_FILE (not COMPOSE), origin the line. */
    public function testSourcesReportsTheUnstrippedKeyForARelativeFileValue(): void
    {
        $dotEnv = new DotEnv();
        $dotEnv->loadPrivateFromString("COMPOSE_FILE=support/docker-compose.yml\n");

        $sources = $dotEnv->sources();

        $this->assertArrayHasKey('COMPOSE_FILE', $sources);
        $this->assertArrayNotHasKey('COMPOSE', $sources);
        $this->assertSame('string', $sources['COMPOSE_FILE']);
    }
}
