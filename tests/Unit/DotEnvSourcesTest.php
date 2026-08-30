<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv\Tests\Unit;

use JardisSupport\DotEnv\DotEnv;
use JardisSupport\DotEnv\Handler\ReadAmbientValue;
use PHPUnit\Framework\TestCase;

/**
 * Proves source visibility: DotEnv::sources() names, per key, where the winning value came from —
 * `env`, `file:<realpath>` or `string` — through the real entry points, across the cascade, the
 * include chain and the _FILE pattern, and without ever exposing a value.
 */
class DotEnvSourcesTest extends TestCase
{
    /** @var array<string> Keys this class reads or writes in the process environment. */
    private const TOUCHED_KEYS = [
        'DB_HOST',
        'DB_PORT',
        'DB_PASSWORD',
        'DB_DSN',
        'API_KEY',
        'APP_ENV',
        ReadAmbientValue::MARKER_KEY,
    ];

    /** @var array<string, string|false> */
    private array $originalEnv = [];

    /** @var array<string, mixed> */
    private array $originalSuperGlobals = [];

    private string $fixturesPath;

    /** @var array<string> Temp directories created by makeAbsoluteFileSecretDir(), removed in tearDown. */
    private array $tempDirs = [];

    protected function setUp(): void
    {
        $this->fixturesPath = dirname(__DIR__) . '/fixtures/sources';

        foreach (self::TOUCHED_KEYS as $key) {
            $this->originalEnv[$key] = getenv($key);
            $this->originalSuperGlobals[$key] = [
                'env' => $_ENV[$key] ?? null,
                'server' => $_SERVER[$key] ?? null,
            ];
            $this->clearKey($key);
        }
    }

    protected function tearDown(): void
    {
        foreach (self::TOUCHED_KEYS as $key) {
            $this->clearKey($key);

            $original = $this->originalEnv[$key];
            if (is_string($original)) {
                putenv($key . '=' . $original);
            }

            $envValue = $this->originalSuperGlobals[$key]['env'] ?? null;
            if ($envValue !== null) {
                $_ENV[$key] = $envValue;
            }

            $serverValue = $this->originalSuperGlobals[$key]['server'] ?? null;
            if ($serverValue !== null) {
                $_SERVER[$key] = $serverValue;
            }
        }

        foreach ($this->tempDirs as $tempDir) {
            @unlink($tempDir . '/.env');
            @rmdir($tempDir);
        }
    }

    /**
     * behaviour changed 2026-08-30: _FILE resolution requires an absolute path (Bescheid Rolf,
     * Gabel G3 env-kollisionen). The static "file-secret" fixture used a relative path, which no
     * longer resolves — this builds a temp .env pointing at the existing secret.txt by absolute
     * path instead, to keep exercising the source-tracking behaviour it always tested.
     */
    private function makeAbsoluteFileSecretDir(): string
    {
        $secretFile = $this->fixturesPath . '/file-secret/secret.txt';
        $tempDir = sys_get_temp_dir() . '/dotenv-sources-file-secret-' . uniqid();
        mkdir($tempDir);
        file_put_contents($tempDir . '/.env', 'DB_PASSWORD_FILE=' . $secretFile . "\n");
        $this->tempDirs[] = $tempDir;

        return $tempDir;
    }

    /** 1: loadPrivate — a key from .env reports that file. */
    public function test01LoadPrivateReportsTheFileTheKeyCameFrom(): void
    {
        $dotEnv = new DotEnv();
        $dotEnv->loadPrivate($this->fixturesPath . '/basic');

        $this->assertSame(
            'file:' . realpath($this->fixturesPath . '/basic/.env'),
            $dotEnv->sources()['DB_HOST']
        );
    }

    /** 2: cascade — the later file overwrites the recorded origin. */
    public function test02CascadeReportsTheLastAssigningFile(): void
    {
        $dotEnv = new DotEnv();
        $dotEnv->loadPrivate($this->fixturesPath . '/cascade');

        $this->assertSame(
            'file:' . realpath($this->fixturesPath . '/cascade/.env.local'),
            $dotEnv->sources()['DB_HOST']
        );
    }

    /** 3: the process environment wins — origin is env, even though the file carries the key. */
    public function test03AmbientValueReportsEnv(): void
    {
        putenv('DB_HOST=ambient');

        $dotEnv = new DotEnv();
        $dotEnv->loadPrivate($this->fixturesPath . '/basic');

        $this->assertSame('env', $dotEnv->sources()['DB_HOST']);
    }

    /** 4: string input reports `string`. */
    public function test04StringInputReportsString(): void
    {
        $dotEnv = new DotEnv();
        $dotEnv->loadPrivateFromString("API_KEY=abc\n");

        $this->assertSame('string', $dotEnv->sources()['API_KEY']);
    }

    /** 5: loadPublic reports the same origin as loadPrivate — the mode changes nothing. */
    public function test05LoadPublicReportsTheSameFileOrigin(): void
    {
        $dotEnv = new DotEnv();
        $dotEnv->loadPublic($this->fixturesPath . '/basic');

        $this->assertSame(
            'file:' . realpath($this->fixturesPath . '/basic/.env'),
            $dotEnv->sources()['DB_HOST']
        );
    }

    /** 6: include — the key reports the included file, not the including one. */
    public function test06IncludedKeyReportsTheIncludedFile(): void
    {
        $dotEnv = new DotEnv();
        $dotEnv->loadPrivate($this->fixturesPath . '/include');

        $this->assertSame(
            'file:' . realpath($this->fixturesPath . '/include/.env.database'),
            $dotEnv->sources()['DB_DSN']
        );
    }

    /** 7: KEY_FILE — the origin is the .env line, not the secret file; the key is the stripped one. */
    public function test07FileSecretReportsTheEnvFileNotTheSecretFile(): void
    {
        $tempDir = $this->makeAbsoluteFileSecretDir();

        $dotEnv = new DotEnv();
        $dotEnv->loadPrivate($tempDir);

        $sources = $dotEnv->sources();

        $this->assertArrayHasKey('DB_PASSWORD', $sources);
        $this->assertArrayNotHasKey('DB_PASSWORD_FILE', $sources);
        $this->assertSame(
            'file:' . realpath($tempDir . '/.env'),
            $sources['DB_PASSWORD']
        );
        $this->assertNotSame(
            'file:' . realpath($this->fixturesPath . '/file-secret/secret.txt'),
            $sources['DB_PASSWORD']
        );
    }

    /** 8: KEY_FILE with an ambient resolved key reports env. */
    public function test08FileSecretWithAmbientKeyReportsEnv(): void
    {
        putenv('DB_PASSWORD=ambient');

        $dotEnv = new DotEnv();
        $dotEnv->loadPrivate($this->makeAbsoluteFileSecretDir());

        $this->assertSame('env', $dotEnv->sources()['DB_PASSWORD']);
    }

    /** 9: two load calls on one instance accumulate instead of resetting. */
    public function test09SourcesAccumulateAcrossLoadCalls(): void
    {
        $dotEnv = new DotEnv();
        $dotEnv->loadPrivate($this->fixturesPath . '/basic');
        $dotEnv->loadPrivateFromString("API_KEY=abc\n");

        $sources = $dotEnv->sources();

        $this->assertSame(
            'file:' . realpath($this->fixturesPath . '/basic/.env'),
            $sources['DB_HOST']
        );
        $this->assertSame('string', $sources['API_KEY']);
    }

    /** 10: sources() carries origins only — same keys as the result, none of its values. */
    public function test10SourcesCarryNoValues(): void
    {
        $dotEnv = new DotEnv();
        $result = $dotEnv->loadPrivate($this->fixturesPath . '/basic');
        $sources = $dotEnv->sources();

        $this->assertSame(array_keys($result), array_keys($sources));

        foreach ($result as $key => $value) {
            $this->assertNotContains((string) $value, array_values($sources), 'value leaked for ' . $key);
        }
    }

    private function clearKey(string $key): void
    {
        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);
    }
}
