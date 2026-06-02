<?php

namespace JardisSupport\DotEnv\Tests\Unit\Handler;

use JardisSupport\DotEnv\Handler\CastUserHome;
use JardisSupport\DotEnv\Handler\VariableRegistry;
use PHPUnit\Framework\TestCase;

class CastUserHomeTest extends TestCase
{
    private CastUserHome $CastUserHome;
    private VariableRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new VariableRegistry();
        $this->CastUserHome = new CastUserHome($this->registry);
    }

    public function testReplaceTildeWithHomeDir()
    {
        $input = '~/documents';
        $homeDir = '/home/user';
        putenv("HOME=$homeDir");

        $result = ($this->CastUserHome)($input);

        $this->assertEquals('/home/user/documents', $result);
        putenv("HOME");
    }

    public function testTildeInMiddleIsPreserved()
    {
        $input = '~/path/~backup';
        $homeDir = '/home/user';
        putenv("HOME=$homeDir");

        $result = ($this->CastUserHome)($input);

        $this->assertEquals('/home/user/path/~backup', $result);
        putenv("HOME");
    }

    public function testTildeNotAtStartDoesNotTrim()
    {
        $input = ' some~path ';

        $result = ($this->CastUserHome)($input);

        $this->assertEquals(' some~path ', $result);
    }

    public function testNoReplacementForStringsWithoutTilde()
    {
        $input = '/path/to/file';

        $result = ($this->CastUserHome)($input);

        $this->assertEquals('/path/to/file', $result);
    }

    public function testExceptionWhenHomeNotSet()
    {
        $input = '~/documents';
        putenv("HOME");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('HOME environment variable is not set');

        ($this->CastUserHome)($input);
    }

    public function testNullInputReturnsNull()
    {
        $result = ($this->CastUserHome)(null);

        $this->assertNull($result);
    }

    public function testRegistryHomeTakesPrecedenceOverGetenv()
    {
        putenv('HOME=/os/home');
        $this->registry->set('HOME', '/custom/path');

        $result = ($this->CastUserHome)('~/logs');

        $this->assertEquals('/custom/path/logs', $result);
        putenv('HOME');
    }

    public function testRegistryTildeValueFallsBackToGetenv()
    {
        putenv('HOME=/os/home');
        $this->registry->set('HOME', '~');

        $result = ($this->CastUserHome)('~/logs');

        $this->assertEquals('/os/home/logs', $result);
        putenv('HOME');
    }

    public function testSimulateWindowsOnLinux()
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $partialMock = $this->getMockBuilder(CastUserHome::class)
                ->setConstructorArgs([$this->registry])
                ->onlyMethods(['getOsType'])
                ->getMock();

            $partialMock->expects($this->once())
                ->method('getOsType')
                ->willReturn('Windows');

            putenv('HOMEDRIVE=C:');
            putenv('HOMEPATH=/Users/user');

            $result = $partialMock('~');

            $this->assertEquals('C:/Users/user', $result);

            putenv('HOMEDRIVE');
            putenv('HOMEPATH');
        } else {
            $this->markTestSkipped('Test only valid on non-Windows OS');
        }
    }

    public function testSimulateLinuxOnWindows()
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $partialMock = $this->getMockBuilder(CastUserHome::class)
                ->setConstructorArgs([$this->registry])
                ->onlyMethods(['getOsType'])
                ->getMock();

            $partialMock->expects($this->once())
                ->method('getOsType')
                ->willReturn('Linux');

            putenv('HOME=/Users/user');

            $result = $partialMock('~');
            $this->assertEquals('/Users/user', $result);
        } else {
            $this->markTestSkipped('Test only valid on Windows OS');
        }
    }

    public function testWindowsWithMissingEnvironmentVariables()
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $partialMock = $this->getMockBuilder(CastUserHome::class)
                ->setConstructorArgs([$this->registry])
                ->onlyMethods(['getOsType'])
                ->getMock();

            $partialMock->expects($this->once())
                ->method('getOsType')
                ->willReturn('Windows');

            putenv('HOMEDRIVE');
            putenv('HOMEPATH');

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('HOME environment variable is not set!');

            $partialMock('~');
        } else {
            $this->markTestSkipped('Test only valid on non-Windows OS');
        }
    }
}
