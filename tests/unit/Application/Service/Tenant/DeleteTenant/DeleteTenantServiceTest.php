<?php

declare(strict_types=1);

namespace Phariscope\MultiTenant\Tests\Application\Service\Tenant\DeleteTenant;

use InvalidArgumentException;
use org\bovigo\vfs\vfsStream;
use Phariscope\MultiTenant\Application\Service\Tenant\DeleteTenant\DeleteTenantService;
use Phariscope\MultiTenant\Infrastructure\TenantShortname\TenantShortnameRegistry;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Filesystem scenarios use mikey179/vfsstream (in-memory vfs:// URLs).
 * TenantShortnameRegistry stays on :memory: because PDO/SQLite cannot open vfs:// paths here.
 */
final class DeleteTenantServiceTest extends TestCase
{
    private ?string $appRoot = null;

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
        $this->appRoot = null;
        $this->restoreDataPath();
        parent::tearDown();
    }

    public function testExecuteRemovesTenantDirectoryAndShortnameMapping(): void
    {
        // Arrange
        $tenantId = 'cl_campus-26_abc1234';
        $this->appRoot = $this->setupVfsAppWithTenant($tenantId, [
            'database' => ['app.sqlite' => 'sqlite'],
            'marker.txt' => 'x',
        ]);
        $tenantDir = $this->tenantDirectory($tenantId);

        $registry = $this->registryInMemory();
        $registry->register($tenantId, 'campus-26');

        $sut = new DeleteTenantService($registry, $this->appRoot);

        // Act
        $sut->execute($tenantId);

        // Assert
        $this->assertDirectoryDoesNotExist($tenantDir);
        $this->assertNull($registry->resolveTenantId('campus-26'));
        $this->assertNull($registry->resolveShortname($tenantId));
    }

    public function testExecuteWorksWhenDataPathIsAlreadyTenantScoped(): void
    {
        // Arrange
        $tenantId = 'cl_scoped_abc1234';
        $this->appRoot = $this->setupVfsAppWithTenant($tenantId, [
            'database' => ['app.sqlite' => 'sqlite'],
        ]);
        $tenantDir = $this->tenantDirectory($tenantId);

        $registry = $this->registryInMemory();
        $registry->register($tenantId, 'scoped');

        $_ENV['DATA_PATH'] = $tenantDir;
        putenv('DATA_PATH=' . $tenantDir);

        $sut = new DeleteTenantService($registry, null);

        // Act
        $sut->execute($tenantId);

        // Assert
        $this->assertDirectoryDoesNotExist($tenantDir);
        $this->assertNull($registry->resolveTenantId('scoped'));
    }

    public function testExecuteIsIdempotentWhenDirectoryMissing(): void
    {
        // Arrange
        $tenantId = 'cl_orphan_abc1234';
        $this->appRoot = $this->setupVfsAppRoot();
        $registry = $this->registryInMemory();
        $registry->register($tenantId, 'orphan');

        $sut = new DeleteTenantService($registry, $this->appRoot);

        // Act
        $sut->execute($tenantId);

        // Assert
        $this->assertNull($registry->resolveTenantId('orphan'));
    }

    public function testExecuteRejectsEmptyTenantId(): void
    {
        // Arrange
        $sut = new DeleteTenantService($this->registryInMemory(), $this->setupVfsAppRoot());
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('tenant_id must not be empty.');

        // Act
        $sut->execute('   ');

        // Assert - PHPUnit verifies the exception
    }

    public function testExecuteTrimsTenantIdBeforeDeletion(): void
    {
        // Arrange
        $tenantId = 'cl_trimmed_abc1234';
        $this->appRoot = $this->setupVfsAppWithTenant($tenantId);
        $tenantDir = $this->tenantDirectory($tenantId);

        $registry = $this->registryInMemory();
        $registry->register($tenantId, 'trimmed');

        $sut = new DeleteTenantService($registry, $this->appRoot);

        // Act
        $sut->execute('  ' . $tenantId . '  ');

        // Assert
        $this->assertDirectoryDoesNotExist($tenantDir);
        $this->assertNull($registry->resolveShortname($tenantId));
    }

    public function testExecuteRejectsSlashInTenantId(): void
    {
        // Arrange
        $sut = new DeleteTenantService($this->registryInMemory(), $this->setupVfsAppRoot());
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid tenant_id');

        // Act
        $sut->execute('foo/bar');

        // Assert - PHPUnit verifies the exception
    }

    public function testExecuteRejectsBackslashInTenantId(): void
    {
        // Arrange
        $sut = new DeleteTenantService($this->registryInMemory(), $this->setupVfsAppRoot());
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid tenant_id');

        // Act
        $sut->execute('foo\\bar');

        // Assert - PHPUnit verifies the exception
    }

    public function testExecuteRejectsPathTraversalInTenantId(): void
    {
        // Arrange
        $sut = new DeleteTenantService($this->registryInMemory(), $this->setupVfsAppRoot());
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid tenant_id');

        // Act
        $sut->execute('../evil');

        // Assert - PHPUnit verifies the exception
    }

    public function testExecuteRemovesTenantWhenApplicationDataPathHasTrailingSlash(): void
    {
        // Arrange
        $tenantId = 'cl_trailing_abc1234';
        $this->appRoot = $this->setupVfsAppWithTenant($tenantId);
        $tenantDir = $this->tenantDirectory($tenantId);

        $registry = $this->registryInMemory();
        $registry->register($tenantId, 'trailing');

        $sut = new DeleteTenantService($registry, $this->appRoot . '/');

        // Act
        $sut->execute($tenantId);

        // Assert
        $this->assertDirectoryDoesNotExist($tenantDir);
    }

    public function testExecuteUsesInjectedRegistryInsteadOfCreatingNewOne(): void
    {
        // Arrange
        $tenantId = 'cl_injected_abc1234';
        vfsStream::setup('root', null, [
            'app' => [
                'tenants' => [
                    $tenantId => [],
                ],
            ],
            'other-app' => [
                'tenants' => [],
            ],
        ]);
        $this->appRoot = vfsStream::url('root/app');
        $otherRoot = vfsStream::url('root/other-app');

        $injectedRegistry = $this->registryInMemory();
        $injectedRegistry->register($tenantId, 'injected');

        $otherRegistry = $this->registryInMemory();
        $otherRegistry->register($tenantId, 'other');

        $sut = new DeleteTenantService($injectedRegistry, $this->appRoot);

        // Act
        $sut->execute($tenantId);

        // Assert
        $this->assertNull($injectedRegistry->resolveShortname($tenantId));
        $this->assertSame('other', $otherRegistry->resolveShortname($tenantId));
        $this->assertDirectoryDoesNotExist($this->tenantDirectory($tenantId));
        $this->assertDirectoryExists($otherRoot . '/tenants');
    }

    public function testExecuteResolvesDataPathFromGetenvWhenUnsetInSuperglobal(): void
    {
        // Arrange
        $tenantId = 'cl_getenv_abc1234';
        $this->appRoot = $this->setupVfsAppWithTenant($tenantId);
        $tenantDir = $this->tenantDirectory($tenantId);

        unset($_ENV['DATA_PATH']);
        putenv('DATA_PATH=' . $this->appRoot);

        $registry = $this->registryInMemory();
        $registry->register($tenantId, 'getenv');

        $sut = new DeleteTenantService($registry);

        // Act
        $sut->execute($tenantId);

        // Assert
        $this->assertDirectoryDoesNotExist($tenantDir);
    }

    public function testResolveApplicationDataPathRestoresEnvWhenItWasUnset(): void
    {
        // Arrange
        $tenantId = 'cl_restore_abc1234';
        $this->appRoot = $this->setupVfsAppWithTenant($tenantId);

        unset($_ENV['DATA_PATH']);
        putenv('DATA_PATH=' . $this->appRoot);

        $registry = $this->registryInMemory();
        $registry->register($tenantId, 'restore');

        $sut = new DeleteTenantService($registry);

        // Act
        $sut->execute($tenantId);

        // Assert
        $this->assertFalse(array_key_exists('DATA_PATH', $_ENV));
    }

    public function testResolveApplicationDataPathRestoresPreviousEnvValue(): void
    {
        // Arrange
        $tenantId = 'cl_prev_abc1234';
        $this->appRoot = $this->setupVfsAppWithTenant($tenantId);

        $_ENV['DATA_PATH'] = '/original/data/path';
        putenv('DATA_PATH=' . $this->appRoot);

        $registry = $this->registryInMemory();
        $registry->register($tenantId, 'prev');

        $sut = new DeleteTenantService($registry);

        // Act
        $sut->execute($tenantId);

        // Assert
        $this->assertSame('/original/data/path', $_ENV['DATA_PATH']);
    }

    public function testExecuteWithEmptyTenantIdDoesNotDeleteTenantsDirectory(): void
    {
        // Arrange
        $tenantId = 'cl_preserved_abc1234';
        $this->appRoot = $this->setupVfsAppWithTenant($tenantId);
        $tenantDir = $this->tenantDirectory($tenantId);

        $registry = $this->registryInMemory();
        $registry->register($tenantId, 'preserved');

        $sut = new DeleteTenantService($registry, $this->appRoot);

        try {
            $sut->execute('   ');
            self::fail('Expected InvalidArgumentException.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('tenant_id must not be empty.', $e->getMessage());
        }

        $this->assertDirectoryExists($tenantDir);
        $this->assertSame('preserved', $registry->resolveShortname($tenantId));
    }

    public function testExecutePrefersSuperglobalDataPathOverGetenv(): void
    {
        // Arrange
        $tenantId = 'cl_envprio_abc1234';
        vfsStream::setup('root', null, [
            'primary-app' => [
                'tenants' => [
                    $tenantId => [],
                ],
            ],
            'secondary-app' => [
                'tenants' => [
                    $tenantId => [],
                ],
            ],
        ]);
        $this->appRoot = vfsStream::url('root/primary-app');
        $otherRoot = vfsStream::url('root/secondary-app');

        $_ENV['DATA_PATH'] = $this->appRoot;
        putenv('DATA_PATH=' . $otherRoot);

        $registry = $this->registryInMemory();
        $registry->register($tenantId, 'envprio');

        $sut = new DeleteTenantService($registry);
        $sut->execute($tenantId);

        $this->assertDirectoryDoesNotExist($this->tenantDirectory($tenantId));
        $this->assertDirectoryExists($otherRoot . '/tenants/' . $tenantId);
    }

    public function testExecuteRequiresDataPathWhenNotInjected(): void
    {
        // Arrange
        unset($_ENV['DATA_PATH']);
        putenv('DATA_PATH');

        $sut = new DeleteTenantService();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DATA_PATH');

        // Act
        $sut->execute('cl_x_abc1234');

        // Assert - PHPUnit verifies the exception
    }

    /**
     * @param array<string, mixed> $tenantContents
     */
    private function setupVfsAppWithTenant(
        string $tenantId,
        array $tenantContents = [],
        string $appSegment = 'app',
    ): string {
        $structure = [
            $appSegment => [
                'tenants' => [
                    $tenantId => $tenantContents,
                ],
            ],
        ];

        vfsStream::setup('root', null, $structure);

        return $this->vfsAppUrl($appSegment);
    }

    private function setupVfsAppRoot(string $appSegment = 'app'): string
    {
        vfsStream::setup('root', null, [
            $appSegment => [
                'tenants' => [],
            ],
        ]);

        return $this->vfsAppUrl($appSegment);
    }

    private function vfsAppUrl(string $appSegment): string
    {
        $url = vfsStream::url('root/' . $appSegment);
        if ($url === '') {
            self::fail('vfsStream::url() must not return an empty string.');
        }

        $this->appRoot = $url;

        return $url;
    }

    private function tenantDirectory(string $tenantId): string
    {
        if ($this->appRoot === null) {
            self::fail('Virtual application root is not initialized.');
        }

        return $this->appRoot . '/tenants/' . $tenantId;
    }

    private function registryInMemory(): TenantShortnameRegistry
    {
        return new TenantShortnameRegistry(':memory:');
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
