<?php

namespace Phariscope\MultiTenant\Tests\Domain\Model\Tenant;

use Phariscope\MultiTenant\Domain\Model\Tenant\TenantId;
use PHPUnit\Framework\TestCase;

class TenantIdTest extends TestCase
{
    public function testTenantIdIsCreatedWithPrefix(): void
    {
        // Arrange
        // (no input: auto-generated id)

        // Act
        $tenantId = new TenantId();

        // Assert
        $this->assertInstanceOf(TenantId::class, $tenantId);
        $this->assertStringStartsWith(TenantId::PREFIX, (string) $tenantId);
    }

    public function testTenantIdAreEqual(): void
    {
        // Arrange
        $tenantId1 = new TenantId('1');
        $tenantId2 = new TenantId('1');

        // Act
        $equals = $tenantId1->equals($tenantId2);

        // Assert
        $this->assertEquals($tenantId1, $tenantId2);
        $this->assertTrue($equals);
    }

    public function testTenantIdAreNotEqual(): void
    {
        // Arrange
        $tenantId1 = new TenantId('1');
        $tenantId2 = new TenantId('2');

        // Act
        $equals = $tenantId1->equals($tenantId2);

        // Assert
        $this->assertNotEquals($tenantId1, $tenantId2);
        $this->assertFalse($equals);
    }

    public function testTenantIdGeneratedNaturalyAreNotEqual(): void
    {
        // Arrange
        $tenantId1 = new TenantId();
        $tenantId2 = new TenantId();

        // Act
        $equals = $tenantId1->equals($tenantId2);

        // Assert
        $this->assertNotEquals($tenantId1, $tenantId2);
        $this->assertFalse($equals);
    }
}
