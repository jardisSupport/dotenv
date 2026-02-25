<?php

namespace JardisSupport\DotEnv\Tests\Unit\Casting;

use JardisSupport\DotEnv\Casting\CastUserHome;
use PHPUnit\Framework\TestCase;

class CastUserHomeTest extends TestCase
{
    private CastUserHome $CastUserHome;

    protected function setUp(): void
    {
        $this->CastUserHome = new CastUserHome();
    }

    public function testReplaceTildeWithHomeDir()
    {
        $input = '~/documents';
        $homeDir = '/home/user';
        putenv("HOME=$homeDir");

        $result = ($this->CastUserHome)($input);

        $this->assertEquals('/home/user/documents', $result);
        putenv("HOME"); // Reset HOME environment variable
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
        putenv("HOME"); // Clear HOME environment variable

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('HOME environment variable is not set');

        ($this->CastUserHome)($input);

    }

    public function testNullInputReturnsNull()
    {
        $result = ($this->CastUserHome)(null);

        $this->assertNull($result);
    }

    public function testSimulateWindowsOnLinux()
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $partialMockGetHomeDir = $this->getMockBuilder(CastUserHome::class)
                ->onlyMethods(['getOsType'])
                ->getMock();

            $partialMockGetHomeDir->expects($this->once())
                ->method('getOsType')
                ->willReturn('Windows');

            putenv('HOMEDRIVE=C:');
            putenv('HOMEPATH=/Users/user');

            $result = $partialMockGetHomeDir('~');

            $this->assertEquals('C:/Users/user', $result);

            putenv('HOMEDRIVE');
            putenv('HOMEPATH');
        }
        else {
            $this->markTestSkipped('Test only valid on non-Windows OS');
        }
    }

    public function testSimulateLinuxOnWindows()
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $partialMockGetHomeDir = $this->getMockBuilder(CastUserHome::class)
                ->onlyMethods(['getOsType'])
                ->getMock();

            $partialMockGetHomeDir->expects($this->once())
                ->method('getOsType')
                ->willReturn('Linux');

            putenv('HOME=/Users/user');

            $result = $partialMockGetHomeDir('~');
            $this->assertEquals('/Users/user', $result);
        }
        else {
            $this->markTestSkipped('Test only valid on Windows OS');
        }
    }

    public function testWindowsWithMissingEnvironmentVariables()
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $partialMockGetHomeDir = $this->getMockBuilder(CastUserHome::class)
                ->onlyMethods(['getOsType'])
                ->getMock();

            $partialMockGetHomeDir->expects($this->once())
                ->method('getOsType')
                ->willReturn('Windows');

            // Stelle sicher, dass beide Windows-Umgebungsvariablen nicht gesetzt sind
            putenv('HOMEDRIVE'); // Clear HOMEDRIVE
            putenv('HOMEPATH');  // Clear HOMEPATH

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('HOME environment variable is not set!');

            $partialMockGetHomeDir('~');
        } else {
            $this->markTestSkipped('Test only valid on non-Windows OS');
        }
    }
}
