<?php

namespace Phariscope\MultiTenant\Domain\Model\Tenant;

class Tenant
{
    public function __construct(
        private TenantId $tenantId,
        private ?string $name = null,
        private ?string $userEmail = null,
    ) {
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getName(): string
    {
        return $this->name ?? '';
    }

    public function getUserEmail(): string
    {
        return $this->userEmail ?? '';
    }
}
