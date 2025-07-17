<?php

namespace Phariscope\MultiTenant\Tests\Domain\Model\Tenant;

use Phariscope\MultiTenant\Domain\Model\Tenant\Tenant;
use Phariscope\MultiTenant\Domain\Model\Tenant\TenantId;
use PHPUnit\Framework\TestCase;

class TenantTest extends TestCase
{
    public function testTenantIsCreated(): void
    {
        $tenant = new Tenant(new TenantId());
        $this->assertInstanceOf(Tenant::class, $tenant);
        $this->assertIsString($tenant->getTenantId());
        $this->assertEquals('', $tenant->getName());
        $this->assertEquals('', $tenant->getUserEmail());
    }

    public function testTenantCreatedWithNameAndUserEmail(): void
    {
        $tenant = new Tenant(new TenantId(), 'Campus26', 'user@campus26.com');
        $this->assertInstanceOf(Tenant::class, $tenant);
        $this->assertIsString($tenant->getTenantId());
        $this->assertEquals('Campus26', $tenant->getName());
        $this->assertEquals('user@campus26.com', $tenant->getUserEmail());
    }
}
