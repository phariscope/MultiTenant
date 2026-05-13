<?php

namespace Phariscope\MultiTenant\Application\Service\Tenant\CreateTenant;

class CreateTenantRequest
{
    public function __construct(
        public readonly string $tenantId,
        public readonly string $tenantName,
        public readonly string $userEmail,
        public readonly ?string $tenantShortname = null,
    ) {
    }
}
