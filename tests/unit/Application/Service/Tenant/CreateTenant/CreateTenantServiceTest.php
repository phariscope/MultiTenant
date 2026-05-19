<?php

namespace Phariscope\MultiTenant\Tests\Application\Service\Tenant\CreateTenant;

use Phariscope\MultiTenant\Application\Service\Tenant\CreateTenant\CreateTenantRequest;
use Phariscope\MultiTenant\Application\Service\Tenant\CreateTenant\CreateTenantService;
use Phariscope\MultiTenant\Infrastructure\Persistence\Tenant\TenantRepositoryInMemory;
use Phariscope\MultiTenant\Infrastructure\TenantShortname\TenantShortnameRegistry;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

class CreateTenantServiceTest extends TestCase
{
    private ?string $tmpBase = null;

    protected function tearDown(): void
    {
        if ($this->tmpBase !== null) {
            (new Filesystem())->remove($this->tmpBase);
            $this->tmpBase = null;
        }
    }

    public function testExecuteRegistersTenantIdAsShortnameWhenShortnameOmitted(): void
    {
        $this->tmpBase = sys_get_temp_dir() . '/mt-cts-' . uniqid('', true);
        $registry = TenantShortnameRegistry::fromApplicationDataPath($this->tmpBase);

        $request = new CreateTenantRequest(
            tenantId: 'am_cl_1234567890',
            tenantName: 'Campus26',
            userEmail: 'user@campus26.com',
        );
        $sut = new CreateTenantService(new TenantRepositoryInMemory(), $registry);

        $sut->execute($request);
        $response = $sut->getResponse();

        $this->assertEquals('am_cl_1234567890', $response->tenantId);
        $this->assertEquals('Campus26', $response->tenantName);
        $this->assertEquals('user@campus26.com', $response->userEmail);
        $this->assertSame('am_cl_1234567890', $registry->resolveTenantId('am_cl_1234567890'));
    }

    public function testExecuteRegistersTenantShortname(): void
    {
        $this->tmpBase = sys_get_temp_dir() . '/mt-cts-' . uniqid('', true);
        $registry = TenantShortnameRegistry::fromApplicationDataPath($this->tmpBase);

        $request = new CreateTenantRequest(
            tenantId: 'am_cl_1234567890',
            tenantName: 'Campus26',
            userEmail: 'user@campus26.com',
            tenantShortname: 'campus-26',
        );
        $sut = new CreateTenantService(new TenantRepositoryInMemory(), $registry);

        $sut->execute($request);

        $this->assertSame('am_cl_1234567890', $registry->resolveTenantId('campus-26'));
    }

    public function testExecuteCallsTenantRepositoryCreate(): void
    {
        $this->tmpBase = sys_get_temp_dir() . '/mt-cts-' . uniqid('', true);
        $registry = TenantShortnameRegistry::fromApplicationDataPath($this->tmpBase);

        $request = new CreateTenantRequest(
            tenantId: 'am_cl_fixed',
            tenantName: 'N',
            userEmail: 'e@e.com',
        );
        $repo = new TenantRepositoryInMemory();
        $sut = new CreateTenantService($repo, $registry);

        $sut->execute($request);

        $tenants = $repo->getTenants();
        $this->assertCount(1, $tenants);
        $this->assertSame('am_cl_fixed', $tenants[0]->getTenantId());
        $this->assertSame('am_cl_fixed', $sut->getResponse()->tenantId);
    }

    public function testExecuteThrowsWhenDataPathMissing(): void
    {
        $hadKey = array_key_exists('DATA_PATH', $_ENV);
        $previous = $hadKey ? $_ENV['DATA_PATH'] : null;
        unset($_ENV['DATA_PATH']);
        putenv('DATA_PATH');

        $request = new CreateTenantRequest(
            tenantId: 'am_cl_x',
            tenantName: 'N',
            userEmail: 'e@e.com',
        );
        $sut = new CreateTenantService(new TenantRepositoryInMemory(), null);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DATA_PATH is not set; cannot write tenant shortname mapping');

        try {
            $sut->execute($request);
        } finally {
            if ($hadKey && is_string($previous)) {
                $_ENV['DATA_PATH'] = $previous;
                putenv('DATA_PATH=' . $previous);
            } else {
                unset($_ENV['DATA_PATH']);
                putenv('DATA_PATH');
            }
        }
    }
}
