<?php

namespace Phariscope\MultiTenant\Application\Service\Tenant\CreateTenant;

use Phariscope\MultiTenant\Domain\Model\Tenant\Tenant;
use Phariscope\MultiTenant\Domain\Model\Tenant\TenantId;
use Phariscope\MultiTenant\Domain\Model\Tenant\TenantRepositoryInterface;

class CreateTenantService
{
    private CreateTenantResponse $response;

    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
    ) {
    }

    public function execute(CreateTenantRequest $request): void
    {

        $tenant = new Tenant(
            tenantId: new TenantId($request->tenantId),
            name: $request->tenantName,
            userEmail: $request->userEmail,
        );
        $this->tenantRepository->create($tenant);

        $this->response = new CreateTenantResponse(
            tenantId: $tenant->getTenantId(),
            tenantName: $tenant->getName(),
            userEmail: $tenant->getUserEmail(),
        );
    }

    public function getResponse(): CreateTenantResponse
    {
        return $this->response;
    }
}
