<?php

namespace Phariscope\MultiTenant\Application\Service\Tenant\CreateTenant;

use Phariscope\MultiTenant\Domain\Model\Tenant\Tenant;
use Phariscope\MultiTenant\Domain\Model\Tenant\TenantId;
use Phariscope\MultiTenant\Domain\Model\Tenant\TenantRepositoryInterface;
use Phariscope\MultiTenant\Infrastructure\TenantShortname\TenantShortnameRegistry;

class CreateTenantService
{
    private CreateTenantResponse $response;

    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
        private readonly ?TenantShortnameRegistry $shortnameRegistry = null,
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

        if ($request->tenantShortname !== null && $request->tenantShortname !== '') {
            $registry = $this->shortnameRegistry ?? TenantShortnameRegistry::tryCreateFromEnv();
            if ($registry === null) {
                throw new \RuntimeException(
                    'tenant_shortname was provided but DATA_PATH is not set; cannot write tenants/tenants.sqlite.'
                );
            }
            $registry->register($tenant->getTenantId(), $request->tenantShortname);
        }

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
