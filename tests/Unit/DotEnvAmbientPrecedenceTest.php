<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv\Tests\Unit;

use JardisSupport\DotEnv\DotEnv;
use JardisSupport\DotEnv\Handler\ReadAmbientValue;
use PHPUnit\Framework\TestCase;

/**
 * Proves the precedence rule: a key already set in the process environment beats the value
 * parsed from a .env file or string — through the real DotEnv entry points, for both loading
 * modes, and without breaking the cascade (the JARDIS_DOTENV_VARS marker).
 */
class DotEnvAmbientPrecedenceTest extends TestCase
{
    /** @var array<string> Keys this class reads or writes in the process environment. */
    private const TOUCHED_KEYS = [
        'DB_HOST',
        'DB_PASSWORD',
        'DATABASE_URL',
        'DEBUG',
        'APP_ENV',
        ReadAmbientValue::MARKER_KEY,
    ];

    /** @var array<string, string|false> */
    private array $originalEnv = [];

    /** @var array<string, mixed> */
    private array $originalSuperGlobals = [];

    private string $fixturesPath;

    protected function setUp(): void
    {
        $this->fixturesPath = dirname(__DIR__) . '/fixtures/ambient';

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
    }

    /** 1: loadPrivate — the process environment beats the file value. */
    public function test01LoadPrivateLetsProcessEnvironmentWin(): void
    {
        putenv('DB_HOST=ambient');

        $result = (new DotEnv())->loadPrivate($this->fixturesPath . '/precedence');

        $this->assertSame('ambient', $result['DB_HOST']);
    }

    /** 2: loadPublic — the winning value is what gets published. */
    public function test02LoadPublicPublishesTheProcessEnvironmentValue(): void
    {
        putenv('DB_HOST=ambient');

        (new DotEnv())->loadPublic($this->fixturesPath . '/precedence');

        $this->assertSame('ambient', $_ENV['DB_HOST']);
        $this->assertSame('ambient', getenv('DB_HOST'));
    }

    /** 3: loadPrivateFromString — same rule for string input. */
    public function test03LoadPrivateFromStringLetsProcessEnvironmentWin(): void
    {
        putenv('DB_HOST=ambient');

        $result = (new DotEnv())->loadPrivateFromString("DB_HOST=file\n");

        $this->assertSame('ambient', $result['DB_HOST']);
    }

    /** 4: loadPublicFromString — same rule for string input. */
    public function test04LoadPublicFromStringLetsProcessEnvironmentWin(): void
    {
        putenv('DB_HOST=ambient');

        (new DotEnv())->loadPublicFromString("DB_HOST=file\n");

        $this->assertSame('ambient', $_ENV['DB_HOST']);
        $this->assertSame('ambient', getenv('DB_HOST'));
    }

    /** 5: the cascade still overrides itself — this library's own putenv() is not ambient. */
    public function test05CascadeStillOverridesItsOwnPublishedValue(): void
    {
        (new DotEnv())->loadPublic($this->fixturesPath . '/cascade');

        $this->assertSame('local', $_ENV['DB_HOST']);
    }

    /** 6: two loadPublic() runs in one process — the second run wins. */
    public function test06SecondLoadPublicRunWinsOverTheFirst(): void
    {
        (new DotEnv())->loadPublic($this->fixturesPath . '/cascade');
        $this->assertSame('local', $_ENV['DB_HOST']);

        (new DotEnv())->loadPublic($this->fixturesPath . '/second');

        $this->assertSame('second', $_ENV['DB_HOST']);
        $this->assertSame('second', getenv('DB_HOST'));
    }

    /** 7: an empty process value counts as not set — the file value wins. */
    public function test07EmptyProcessValueDoesNotWin(): void
    {
        putenv('DB_HOST=');

        $result = (new DotEnv())->loadPrivate($this->fixturesPath . '/precedence');

        $this->assertSame('file', $result['DB_HOST']);
    }

    /** 8: the winning value runs through the cast chain and the raw-key exemption. */
    public function test08AmbientValueRunsThroughCastChainAndRawKeyRule(): void
    {
        putenv('DEBUG=true');
        putenv('DB_PASSWORD=123456');

        $dotEnv = new DotEnv();
        $dotEnv->addRawKeys(['_PASSWORD']);

        $result = $dotEnv->loadPrivate($this->fixturesPath . '/precedence');

        $this->assertTrue($result['DEBUG']);
        $this->assertSame('123456', $result['DB_PASSWORD']);
    }

    /** 9: ${VAR} substitution resolves against the winning value. */
    public function test09VariableSubstitutionUsesTheWinningValue(): void
    {
        putenv('DB_HOST=ambient');

        $result = (new DotEnv())->loadPrivate($this->fixturesPath . '/precedence');

        $this->assertSame('mysql://ambient', $result['DATABASE_URL']);
    }

    /** 10: KEY_FILE — the resolved key wins, the secret file is never read. */
    public function test10FileSecretIsSkippedWhenResolvedKeyIsAmbient(): void
    {
        putenv('DB_PASSWORD=ambient');

        $result = (new DotEnv())->loadPrivate($this->fixturesPath . '/file-secret');

        $this->assertSame('ambient', $result['DB_PASSWORD']);
    }

    /** 11: the marker lists every published key once. */
    public function test11MarkerListsPublishedKeysWithoutDuplicates(): void
    {
        $dotEnv = new DotEnv();
        $dotEnv->loadPublic($this->fixturesPath . '/precedence');
        $dotEnv->loadPublic($this->fixturesPath . '/precedence');

        $marker = getenv(ReadAmbientValue::MARKER_KEY);
        $this->assertIsString($marker);

        $keys = explode(',', $marker);
        $this->assertSame(['DB_HOST', 'DEBUG', 'DATABASE_URL', 'DB_PASSWORD'], $keys);
        $this->assertSame($keys, array_values(array_unique($keys)));
        $this->assertSame($marker, $_ENV[ReadAmbientValue::MARKER_KEY]);
    }

    private function clearKey(string $key): void
    {
        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);
    }
}
