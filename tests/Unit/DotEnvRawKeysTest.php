<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv\Tests\Unit;

use JardisSupport\DotEnv\DotEnv;
use PHPUnit\Framework\TestCase;

/**
 * AK1.1/AK1.2: proves the raw-key cast exemption through the real DotEnv::loadPrivate()
 * cascade (real fixture files, not an injected reader) and that unregistered behaviour is
 * unchanged (v1.1.5 parity).
 */
class DotEnvRawKeysTest extends TestCase
{
    private string $fixturesPath;

    protected function setUp(): void
    {
        $this->fixturesPath = dirname(__DIR__) . '/fixtures/raw-keys';
    }

    public function testUnregisteredKeyIsCastAsBeforeV1_1_5(): void
    {
        $dotEnv = new DotEnv();

        $result = $dotEnv->loadPrivate($this->fixturesPath . '/bool-value');

        $this->assertFalse($result['DB_PASSWORD']);
    }

    public function testUnregisteredNumericKeyIsCastToInt(): void
    {
        $dotEnv = new DotEnv();

        $result = $dotEnv->loadPrivate($this->fixturesPath . '/numeric-value');

        $this->assertSame(123456, $result['DB_PASSWORD']);
    }

    public function testRegisteredRawKeySurvivesBoolLikeValueAsString(): void
    {
        $dotEnv = new DotEnv();
        $dotEnv->addRawKeys(['DB_PASSWORD']);

        $result = $dotEnv->loadPrivate($this->fixturesPath . '/bool-value');

        $this->assertSame('false', $result['DB_PASSWORD']);
    }

    public function testRegisteredRawKeySurvivesNumericValueAsString(): void
    {
        $dotEnv = new DotEnv();
        $dotEnv->addRawKeys(['DB_PASSWORD']);

        $result = $dotEnv->loadPrivate($this->fixturesPath . '/numeric-value');

        $this->assertSame('123456', $result['DB_PASSWORD']);
    }

    public function testRegisteredRawKeySuffixSurvivesViaKeyFileResolution(): void
    {
        $dotEnv = new DotEnv();
        $dotEnv->addRawKeys(['_PASSWORD']);

        $result = $dotEnv->loadPrivate($this->fixturesPath . '/file-secret');

        // The _FILE suffix is stripped before the raw-key check: DB_PASSWORD_FILE -> DB_PASSWORD.
        $this->assertSame('false', $result['DB_PASSWORD']);
        $this->assertArrayNotHasKey('DB_PASSWORD_FILE', $result);
    }

    public function testKeyFileWithoutRawKeyRegistrationStillCasts(): void
    {
        $dotEnv = new DotEnv();

        $result = $dotEnv->loadPrivate($this->fixturesPath . '/file-secret');

        $this->assertFalse($result['DB_PASSWORD']);
    }

    public function testAddRawKeysIsAccumulatingAndIdempotent(): void
    {
        $dotEnv = new DotEnv();
        $dotEnv->addRawKeys(['DB_PASSWORD']);
        $dotEnv->addRawKeys(['db_password']);

        $result = $dotEnv->loadPrivate($this->fixturesPath . '/bool-value');

        $this->assertSame('false', $result['DB_PASSWORD']);
    }

    public function testRawKeyStillRunsAddHandlerValueHandlerAndStaysString(): void
    {
        $dotEnv = new DotEnv();
        $dotEnv->addRawKeys(['_PASSWORD']);
        $dotEnv->addHandler(new StripSecretWrapper(), prepend: true);

        $result = $dotEnv->loadPrivate($this->fixturesPath . '/secret-value');

        // Handler unwraps secret(false) -> "false"; the raw key keeps it a string, not bool.
        $this->assertSame('false', $result['DB_PASSWORD']);
    }

    public function testNonRawKeyWithPrependedHandlerRunsBeforeCasts(): void
    {
        $dotEnv = new DotEnv();
        $dotEnv->addHandler(new StripSecretWrapper(), prepend: true);

        $result = $dotEnv->loadPrivate($this->fixturesPath . '/secret-value');

        // Regression guard: without raw-key registration the handler result is still cast.
        $this->assertFalse($result['DB_PASSWORD']);
    }

    public function testRawKeyHandlerAppliesInStringLoadPath(): void
    {
        $dotEnv = new DotEnv();
        $dotEnv->addRawKeys(['_PASSWORD']);
        $dotEnv->addHandler(new StripSecretWrapper(), prepend: true);

        $result = $dotEnv->loadPrivateFromString("DB_PASSWORD=secret(false)\n");

        $this->assertSame('false', $result['DB_PASSWORD']);
    }

    public function testRemoveHandlerRemovesValueHandlerFromRawPath(): void
    {
        $dotEnv = new DotEnv();
        $dotEnv->addRawKeys(['_PASSWORD']);
        $dotEnv->addHandler(new StripSecretWrapper(), prepend: true);
        $dotEnv->removeHandler(StripSecretWrapper::class);

        $result = $dotEnv->loadPrivate($this->fixturesPath . '/secret-value');

        $this->assertSame('secret(false)', $result['DB_PASSWORD']);
    }
}

/**
 * SecretHandler-like value handler stub: unwraps secret(...) and returns the inner string.
 */
class StripSecretWrapper
{
    public function __invoke(?string $value = null): ?string
    {
        if ($value !== null && str_starts_with($value, 'secret(') && str_ends_with($value, ')')) {
            return substr($value, 7, -1);
        }

        return $value;
    }
}
