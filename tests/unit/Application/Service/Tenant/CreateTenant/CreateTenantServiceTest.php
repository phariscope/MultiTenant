<?php

namespace Phariscope\MultiTenant\Tests\Application\Service\Tenant\CreateTenant;

use Phariscope\MultiTenant\Application\Service\Tenant\CreateTenant\CreateTenantRequest;
use Phariscope\MultiTenant\Application\Service\Tenant\CreateTenant\CreateTenantService;
use Phariscope\MultiTenant\Infrastructure\Persistence\Tenant\TenantRepository;
use Phariscope\MultiTenant\Infrastructure\Persistence\Tenant\TenantRepositoryInMemory;
use PHPUnit\Framework\TestCase;

class CreateTenantServiceTest extends TestCase
{
    public function testExecute(): void
    {
        $request = new CreateTenantRequest(
            tenantId: 'am_cl_1234567890',
            tenantName: 'Campus26',
            userEmail: 'user@campus26.com',
        );
        $sut = new CreateTenantService(new TenantRepositoryInMemory());
        $sut->execute($request);

        $response = $sut->getResponse();
        $this->assertEquals('am_cl_1234567890', $response->tenantId);
        $this->assertEquals('Campus26', $response->tenantName);
        $this->assertEquals('user@campus26.com', $response->userEmail);
    }
}
