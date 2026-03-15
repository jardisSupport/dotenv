<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv\Tests\Unit\Handler;

use JardisSupport\DotEnv\Handler\VariableRegistry;
use PHPUnit\Framework\TestCase;

class VariableRegistryTest extends TestCase
{
    private VariableRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new VariableRegistry();
    }

    public function testSetAndGet(): void
    {
        $this->registry->set('KEY', 'value');

        $this->assertSame('value', $this->registry->get('KEY'));
    }

    public function testGetReturnsNullForUnknownKey(): void
    {
        $this->assertNull($this->registry->get('UNKNOWN_REGISTRY_KEY'));
    }

    public function testGetFallsBackToGetenv(): void
    {
        putenv('REGISTRY_FALLBACK_TEST=os_value');

        $this->assertSame('os_value', $this->registry->get('REGISTRY_FALLBACK_TEST'));

        putenv('REGISTRY_FALLBACK_TEST');
    }

    public function testRegistryValueOverridesGetenv(): void
    {
        putenv('REGISTRY_OVERRIDE_TEST=os_value');
        $this->registry->set('REGISTRY_OVERRIDE_TEST', 'registry_value');

        $this->assertSame('registry_value', $this->registry->get('REGISTRY_OVERRIDE_TEST'));

        putenv('REGISTRY_OVERRIDE_TEST');
    }

    public function testReset(): void
    {
        $this->registry->set('KEY', 'value');
        $this->registry->reset();

        $this->assertNull($this->registry->get('KEY'));
    }
}
