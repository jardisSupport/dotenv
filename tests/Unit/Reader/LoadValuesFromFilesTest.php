<?php

namespace JardisSupport\DotEnv\Tests\Unit\Reader;

use JardisSupport\DotEnv\Handler\CastTypeHandler;
use JardisSupport\DotEnv\Reader\LoadValuesFromFiles;
use PHPUnit\Framework\TestCase;

class LoadValuesFromFilesTest extends TestCase
{
    private CastTypeHandler $castTypeHandler;
    private LoadValuesFromFiles $loadValuesFromFiles;

    /** @var string|false */
    private $originalHome;

    protected function setUp(): void
    {
        $this->castTypeHandler = $this->createMock(CastTypeHandler::class);
        $this->loadValuesFromFiles = new LoadValuesFromFiles($this->castTypeHandler);

        // A key set in the process environment wins over the file value. The fixture defines its
        // own HOME, so the ambient HOME (always set inside a container) is taken out of the way.
        $this->originalHome = getenv('HOME');
        putenv('HOME');
    }

    protected function tearDown(): void
    {
        putenv('HOME');

        if (is_string($this->originalHome)) {
            putenv('HOME=' . $this->originalHome);
        }
    }

    public function testReturnsMergedValuesNotPublic()
    {
        $this->castTypeHandler
            ->method('__invoke')
            ->willReturnArgument(0); // Rückgabe des gleichen Werts für einfache Tests

        $file = [dirname(__DIR__) . '/../fixtures/.env'];

        $result = ($this->loadValuesFromFiles)($file, false);

        $expected = [
            'DB_HOST' => 'prodHost',
            'DB_NAME' => 'prodName',
            'HOME' => '~',
            'DATABASE_URL' => 'mysql://${DB_HOST}:${DB_NAME}@localhost',
            'BOOL_VAR' => 'true',
            'INT_VAR' => '1',
            'TEST' => '[a=>1,2,b=>true,4,5,6,7,test=>[1,2,3,4]]'
        ];

        $this->assertEquals($expected, $result);
    }

    public function testInvokeSkipsUnreadableFiles()
    {
        $this->castTypeHandler
            ->method('__invoke')
            ->willReturnArgument(0); // Rückgabe des gleichen Werts für einfache Tests

        $file = [dirname(__DIR__) . '/../fixtures/.notFoundenv'];

        $result = ($this->loadValuesFromFiles)($file, false);

        $this->assertEquals([], $result);
    }

    public function testZeroValueIsProperlyTrimmed(): void
    {
        $this->castTypeHandler
            ->method('__invoke')
            ->willReturnArgument(0);

        $file = [dirname(__DIR__) . '/../fixtures/.env.zero'];

        $result = ($this->loadValuesFromFiles)($file, false);

        $this->assertSame('0', $result['ZERO_VAR']);
        $this->assertSame('0', $result['ZERO_PADDED']);
        $this->assertSame('', $result['EMPTY_VAR']);
    }

    public function testLoadFileValuesParsesValidRowsPublicMode()
    {
        $this->castTypeHandler
            ->method('__invoke')
            ->willReturnCallback(function ($value) {
                return strtoupper($value);
            });

        $file = [dirname(__DIR__) . '/../fixtures/.env'];

        $result = ($this->loadValuesFromFiles)($file);

        $this->assertEquals([], $result);
        // putenv() receives the raw string, not the cast value
        $this->assertEquals('prodHost', getenv('DB_HOST'));
        $this->assertEquals('prodName', getenv('DB_NAME'));
        // $_ENV/$_SERVER receive the cast value
        $this->assertEquals('PRODHOST', $_ENV['DB_HOST']);
        $this->assertEquals('PRODNAME', $_ENV['DB_NAME']);
    }
}
