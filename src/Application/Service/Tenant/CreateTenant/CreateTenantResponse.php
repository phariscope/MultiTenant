<?php

namespace Phariscope\MultiTenant\Application\Service\Tenant\CreateTenant;

use Phariscope\MultiTenant\Application\Service\Shared\Response;

class CreateTenantResponse implements Response
{
    public function __construct(
        public readonly string $tenantId,
        public readonly string $tenantShortname = '',
    ) {
    }
}
