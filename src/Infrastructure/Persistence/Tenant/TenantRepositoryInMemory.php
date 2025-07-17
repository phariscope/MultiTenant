<?php

namespace Phariscope\MultiTenant\Infrastructure\Persistence\Tenant;

use Phariscope\MultiTenant\Domain\Model\Tenant\Tenant;
use Phariscope\MultiTenant\Domain\Model\Tenant\TenantRepositoryInterface;

class TenantRepositoryInMemory implements TenantRepositoryInterface
{
    /** @var Tenant[] */
    private array $tenants = [];

    public function create(Tenant $tenant): void
    {
        $this->tenants[] = $tenant;
    }

    /**
     * @return Tenant[]
     */
    public function getTenants(): array
    {
        return $this->tenants;
    }
}
