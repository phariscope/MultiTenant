<?php

namespace Phariscope\MultiTenant\Tests\Infrastructure\Persistence\Tenant;

use Phariscope\MultiTenant\Domain\Model\Tenant\Tenant;
use Phariscope\MultiTenant\Domain\Model\Tenant\TenantId;
use Phariscope\MultiTenant\Infrastructure\Persistence\Tenant\TenantRepositoryInMemory;
use PHPUnit\Framework\TestCase;

class TenantRepositoryInMemoryTest extends TestCase
{
    public function testCreateTenant(): void
    {
        $tenantRepository = new TenantRepositoryInMemory();
        $tenant = new Tenant(new TenantId(), 'Campus26', 'user@campus26.com');
        $tenantRepository->create($tenant);
        $this->assertCount(1, $tenantRepository->getTenants());
    }
}
