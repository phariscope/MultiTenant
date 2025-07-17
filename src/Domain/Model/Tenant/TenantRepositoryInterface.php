<?php

namespace Phariscope\MultiTenant\Domain\Model\Tenant;

interface TenantRepositoryInterface
{
    public function create(Tenant $tenant): void;
}
