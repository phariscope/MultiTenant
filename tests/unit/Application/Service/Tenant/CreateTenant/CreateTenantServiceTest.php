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

    public function testExecute(): void
    {
        // Arrange
        $request = new CreateTenantRequest(
            tenantId: 'am_cl_1234567890',
            tenantName: 'Campus26',
            userEmail: 'user@campus26.com',
        );
        $sut = new CreateTenantService(new TenantRepositoryInMemory());

        // Act
        $sut->execute($request);
        $response = $sut->getResponse();

        // Assert
        $this->assertEquals('am_cl_1234567890', $response->tenantId);
        $this->assertEquals('Campus26', $response->tenantName);
        $this->assertEquals('user@campus26.com', $response->userEmail);
    }

    public function testExecuteRegistersTenantShortname(): void
    {
        // Arrange
        $this->tmpBase = sys_get_temp_dir() . '/mt-cts-' . uniqid('', true);
        $registry = TenantShortnameRegistry::fromApplicationDataPath($this->tmpBase);

        $request = new CreateTenantRequest(
            tenantId: 'am_cl_1234567890',
            tenantName: 'Campus26',
            userEmail: 'user@campus26.com',
            tenantShortname: 'campus-26',
        );
        $sut = new CreateTenantService(new TenantRepositoryInMemory(), $registry);

        // Act
        $sut->execute($request);

        // Assert
        $this->assertSame('am_cl_1234567890', $registry->resolveTenantId('campus-26'));
    }

    public function testExecuteCallsTenantRepositoryCreate(): void
    {
        // Arrange
        $request = new CreateTenantRequest(
            tenantId: 'am_cl_fixed',
            tenantName: 'N',
            userEmail: 'e@e.com',
        );
        $repo = new TenantRepositoryInMemory();
        $sut = new CreateTenantService($repo);

        // Act
        $sut->execute($request);

        // Assert
        $tenants = $repo->getTenants();
        $this->assertCount(1, $tenants);
        $this->assertSame('am_cl_fixed', $tenants[0]->getTenantId());
        $this->assertSame('am_cl_fixed', $sut->getResponse()->tenantId);
    }

    public function testExecuteThrowsWhenTenantShortnameWithoutDataPathOrRegistry(): void
    {
        // Arrange
        $hadKey = array_key_exists('DATA_PATH', $_ENV);
        $previous = $hadKey ? $_ENV['DATA_PATH'] : null;
        unset($_ENV['DATA_PATH']);
        putenv('DATA_PATH');

        $request = new CreateTenantRequest(
            tenantId: 'am_cl_x',
            tenantName: 'N',
            userEmail: 'e@e.com',
            tenantShortname: 'any-slug',
        );
        $sut = new CreateTenantService(new TenantRepositoryInMemory(), null);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tenant_shortname was provided but DATA_PATH is not set');

        try {
            // Act
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

        // Assert - PHPUnit verifies the exception
    }
}
