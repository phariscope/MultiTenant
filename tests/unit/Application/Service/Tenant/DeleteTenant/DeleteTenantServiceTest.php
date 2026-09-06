<?php

declare(strict_types=1);

namespace Phariscope\MultiTenant\Tests\Application\Service\Tenant\DeleteTenant;

use InvalidArgumentException;
use Phariscope\MultiTenant\Application\Service\Tenant\DeleteTenant\DeleteTenantService;
use Phariscope\MultiTenant\Infrastructure\TenantShortname\TenantShortnameRegistry;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

final class DeleteTenantServiceTest extends TestCase
{
    private ?string $tmpBase = null;

    private ?string $savedDataPath = null;

    private bool $hadDataPath = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hadDataPath = array_key_exists('DATA_PATH', $_ENV);
        $this->savedDataPath = $this->hadDataPath && is_string($_ENV['DATA_PATH'] ?? null)
            ? $_ENV['DATA_PATH']
            : null;
    }

    protected function tearDown(): void
    {
        if ($this->tmpBase !== null) {
            (new Filesystem())->remove($this->tmpBase);
            $this->tmpBase = null;
        }
        $this->restoreDataPath();
        parent::tearDown();
    }

    public function testExecuteRemovesTenantDirectoryAndShortnameMapping(): void
    {
        $this->tmpBase = sys_get_temp_dir() . '/mt-dts-' . uniqid('', true);
        $tenantId = 'cl_campus-26_abc1234';
        $tenantDir = $this->tmpBase . '/tenants/' . $tenantId;
        mkdir($tenantDir . '/database', 0775, true);
        file_put_contents($tenantDir . '/database/app.sqlite', 'sqlite');
        file_put_contents($tenantDir . '/marker.txt', 'x');

        $registry = TenantShortnameRegistry::fromApplicationDataPath($this->tmpBase);
        $registry->register($tenantId, 'campus-26');

        $sut = new DeleteTenantService($registry, $this->tmpBase);
        $sut->execute($tenantId);

        $this->assertDirectoryDoesNotExist($tenantDir);
        $this->assertNull($registry->resolveTenantId('campus-26'));
        $this->assertNull($registry->resolveShortname($tenantId));
    }

    public function testExecuteWorksWhenDataPathIsAlreadyTenantScoped(): void
    {
        $this->tmpBase = sys_get_temp_dir() . '/mt-dts-' . uniqid('', true);
        $appRoot = $this->tmpBase;
        $tenantId = 'cl_scoped_abc1234';
        $tenantDir = $appRoot . '/tenants/' . $tenantId;
        mkdir($tenantDir . '/database', 0775, true);
        file_put_contents($tenantDir . '/database/app.sqlite', 'sqlite');

        $registry = TenantShortnameRegistry::fromApplicationDataPath($appRoot);
        $registry->register($tenantId, 'scoped');

        $_ENV['DATA_PATH'] = $tenantDir;
        putenv('DATA_PATH=' . $tenantDir);

        $sut = new DeleteTenantService(null, null);
        $sut->execute($tenantId);

        $this->assertDirectoryDoesNotExist($tenantDir);
        $this->assertNull(
            TenantShortnameRegistry::fromApplicationDataPath($appRoot)->resolveTenantId('scoped')
        );
    }

    public function testExecuteIsIdempotentWhenDirectoryMissing(): void
    {
        $this->tmpBase = sys_get_temp_dir() . '/mt-dts-' . uniqid('', true);
        $tenantId = 'cl_orphan_abc1234';
        $registry = TenantShortnameRegistry::fromApplicationDataPath($this->tmpBase);
        $registry->register($tenantId, 'orphan');

        $sut = new DeleteTenantService($registry, $this->tmpBase);
        $sut->execute($tenantId);

        $this->assertNull($registry->resolveTenantId('orphan'));
    }

    public function testExecuteRejectsEmptyTenantId(): void
    {
        $sut = new DeleteTenantService(new TenantShortnameRegistry(':memory:'), sys_get_temp_dir());

        $this->expectException(InvalidArgumentException::class);
        $sut->execute('   ');
    }

    public function testExecuteRejectsPathTraversalInTenantId(): void
    {
        $sut = new DeleteTenantService(new TenantShortnameRegistry(':memory:'), sys_get_temp_dir());

        $this->expectException(InvalidArgumentException::class);
        $sut->execute('../evil');
    }

    public function testExecuteRequiresDataPathWhenNotInjected(): void
    {
        unset($_ENV['DATA_PATH']);
        putenv('DATA_PATH');

        $sut = new DeleteTenantService();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DATA_PATH');
        $sut->execute('cl_x_abc1234');
    }

    private function restoreDataPath(): void
    {
        if ($this->hadDataPath && is_string($this->savedDataPath)) {
            $_ENV['DATA_PATH'] = $this->savedDataPath;
            putenv('DATA_PATH=' . $this->savedDataPath);
        } else {
            unset($_ENV['DATA_PATH']);
            putenv('DATA_PATH');
        }
    }
}
