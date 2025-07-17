<?php

namespace Phariscope\MultiTenant\Tests\Domain\Model\Tenant;

use Phariscope\MultiTenant\Domain\Model\Tenant\TenantId;
use PHPUnit\Framework\TestCase;

class TenantIdTest extends TestCase
{
    public function testTenantIdIsCreatedWithPrefix(): void
    {
        $TenantId = new TenantId();
        $this->assertInstanceOf(TenantId::class, $TenantId);
        $this->assertStringStartsWith(TenantId::PREFIX, (string)$TenantId);
    }

    public function testTenantIdAreEqual(): void
    {
        $TenantId1 = new TenantId('1');
        $TenantId2 = new TenantId('1');
        $this->assertEquals($TenantId1, $TenantId2);
        $this->assertTrue($TenantId1->equals($TenantId2));
    }
    public function testTenantIdAreNotEqual(): void
    {
        $TenantId1 = new TenantId('1');
        $TenantId2 = new TenantId('2');
        $this->assertNotEquals($TenantId1, $TenantId2);
        $this->assertFalse($TenantId1->equals($TenantId2));
    }
    public function testTenantIdGeneratedNaturalyAreNotEqual(): void
    {
        $TenantId1 = new TenantId();
        $TenantId2 = new TenantId();
        $this->assertNotEquals($TenantId1, $TenantId2);
        $this->assertFalse($TenantId1->equals($TenantId2));
    }
}
