<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv\Tests\Unit\Handler;

use JardisSupport\DotEnv\Handler\MatchesRawKey;
use PHPUnit\Framework\TestCase;

class MatchesRawKeyTest extends TestCase
{
    public function testNoRawKeysRegisteredMatchesNothing(): void
    {
        $matcher = new MatchesRawKey();

        $this->assertFalse($matcher('DB_PASSWORD'));
    }

    public function testSuffixMatchIsCaseInsensitive(): void
    {
        $matcher = new MatchesRawKey();
        $matcher->addRawKeys(['_password']);

        $this->assertTrue($matcher('DB_PASSWORD'));
        $this->assertTrue($matcher('db_password'));
        $this->assertTrue($matcher('Legacy_DB_Password'));
    }

    public function testExactKeyMatches(): void
    {
        $matcher = new MatchesRawKey();
        $matcher->addRawKeys(['API_KEY']);

        $this->assertTrue($matcher('API_KEY'));
        $this->assertTrue($matcher('api_key'));
    }

    public function testSubstringInTheMiddleDoesNotMatch(): void
    {
        $matcher = new MatchesRawKey();
        $matcher->addRawKeys(['PASS']);

        // "PASS" occurs in the middle, not at the end -> must not match
        $this->assertFalse($matcher('PASSCODE_HINT'));
        // "PASS" at the end -> suffix match
        $this->assertTrue($matcher('MY_PASS'));
    }

    public function testMultipleAddRawKeysCallsAccumulate(): void
    {
        $matcher = new MatchesRawKey();
        $matcher->addRawKeys(['_PASSWORD']);
        $matcher->addRawKeys(['_TOKEN']);

        $this->assertTrue($matcher('DB_PASSWORD'));
        $this->assertTrue($matcher('API_TOKEN'));
    }

    public function testAddRawKeysIsIdempotent(): void
    {
        $matcher = new MatchesRawKey();
        $matcher->addRawKeys(['_PASSWORD']);
        $matcher->addRawKeys(['_password']);
        $matcher->addRawKeys(['_PASSWORD']);

        // Repeated registration of the same (case-insensitive) key/suffix must not change
        // the observable matching behaviour.
        $this->assertTrue($matcher('DB_PASSWORD'));
        $this->assertFalse($matcher('DB_TOKEN'));
    }
}
