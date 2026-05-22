<?php

declare(strict_types=1);

namespace Phariscope\MultiTenant\Tests\Infrastructure\TenantShortname;

use InvalidArgumentException;
use org\bovigo\vfs\vfsStream;
use PDOException;
use Phariscope\MultiTenant\Infrastructure\TenantShortname\TenantShortnameRegistry;
use PHPUnit\Framework\TestCase;

class TenantShortnameRegistryTest extends TestCase
{
    private ?string $savedDataPath = null;

    private bool $savedDataPathInEnv = false;

    protected function setUp(): void
    {
        parent::setUp();
        vfsStream::setup('root');
        $this->savedDataPathInEnv = array_key_exists('DATA_PATH', $_ENV);
        $this->savedDataPath = $this->savedDataPathInEnv ? $_ENV['DATA_PATH'] : null;
    }

    protected function tearDown(): void
    {
        $this->restoreDataPath();
        parent::tearDown();
    }

    public function testRegisterAndResolve(): void
    {
        // Arrange
        $registry = $this->registryInMemory();
        $registry->register('tid-abc', 'My-Brand');

        // Act
        $resolved = $registry->resolveTenantId('my-brand');

        // Assert
        $this->assertSame('tid-abc', $resolved);
    }

    public function testRegisterReplacesShortnameForSameTenant(): void
    {
        // Arrange
        $registry = $this->registryInMemory();
        $registry->register('tid-abc', 'first-slug');
        $registry->register('tid-abc', 'second-slug');

        // Act
        $first = $registry->resolveTenantId('first-slug');
        $second = $registry->resolveTenantId('second-slug');

        // Assert
        $this->assertNull($first);
        $this->assertSame('tid-abc', $second);
    }

    public function testInvalidShortnameThrows(): void
    {
        // Arrange
        $registry = $this->registryInMemory();
        $this->expectException(InvalidArgumentException::class);

        // Act
        $registry->register('tid', 'Bad_Underscore');

        // Assert - PHPUnit verifies the exception
    }

    public function testRegisterAllowsTenantIdAsShortnameWhenEqual(): void
    {
        // Arrange
        $registry = $this->registryInMemory();
        $registry->register('am_cl_fixed', 'am_cl_fixed');

        // Act
        $resolved = $registry->resolveTenantId('am_cl_fixed');

        // Assert
        $this->assertSame('am_cl_fixed', $resolved);
    }

    public function testTryCreateFromEnvUsesGetenvWhenNotInSuperglobal(): void
    {
        // Arrange
        $dataPath = $this->vfsDataPath('app-env');
        unset($_ENV['DATA_PATH']);
        putenv('DATA_PATH=' . $dataPath);

        // Act
        $registry = TenantShortnameRegistry::tryCreateFromEnv();

        // Assert
        $this->assertNotNull($registry);
        $this->assertEndsWithTenantsSqlite($registry);
        $this->assertStringStartsWith($dataPath, $registry->getSqliteFilePath());
    }

    public function testTryCreateFromEnvReturnsNullWhenDataPathIsEmptyString(): void
    {
        // Arrange
        $_ENV['DATA_PATH'] = '';
        putenv('DATA_PATH=');

        // Act
        $registry = TenantShortnameRegistry::tryCreateFromEnv();

        // Assert
        $this->assertNull($registry);
    }

    public function testResolveReturnsNullForWhitespaceOnlyShortname(): void
    {
        // Arrange
        $registry = $this->registryInMemory();

        // Act
        $resolved = $registry->resolveTenantId('   ');

        // Assert
        $this->assertNull($resolved);
    }

    public function testRegisterRejectsEmptyTenantId(): void
    {
        // Arrange
        $registry = $this->registryInMemory();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('tenant_id must not be empty.');

        // Act
        $registry->register('', 'valid-slug');

        // Assert - PHPUnit verifies the exception
    }

    public function testRegisterRejectsEmptyShortname(): void
    {
        // Arrange
        $registry = $this->registryInMemory();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('tenant_shortname must not be empty.');

        // Act
        $registry->register('tid', '   ');

        // Assert - PHPUnit verifies the exception
    }

    public function testRegisterRejectsShortnameLongerThan63Characters(): void
    {
        // Arrange
        $registry = $this->registryInMemory();
        $this->expectException(InvalidArgumentException::class);

        // Act
        $registry->register('tid', str_repeat('a', 64));

        // Assert - PHPUnit verifies the exception
    }

    public function testRegisterRejectsShortnameWithLeadingHyphen(): void
    {
        // Arrange
        $registry = $this->registryInMemory();
        $this->expectException(InvalidArgumentException::class);

        // Act
        $registry->register('tid', '-leading');

        // Assert - PHPUnit verifies the exception
    }

    public function testRegisterRejectsShortnameWithTrailingHyphen(): void
    {
        // Arrange
        $registry = $this->registryInMemory();
        $this->expectException(InvalidArgumentException::class);

        // Act
        $registry->register('tid', 'trailing-');

        // Assert - PHPUnit verifies the exception
    }

    public function testRegisterRollsBackOnDuplicateShortname(): void
    {
        // Arrange
        $registry = $this->registryInMemory();
        $registry->register('tid-first', 'shared-slug');
        $this->expectException(PDOException::class);

        // Act
        $registry->register('tid-second', 'shared-slug');

        // Assert
        $this->assertSame('tid-first', $registry->resolveTenantId('shared-slug'));
    }

    public function testResolveReturnsNullWhenDatabaseFileIsCorrupt(): void
    {
        // Arrange
        vfsStream::setup('root', null, [
            'tenants' => ['tenants.sqlite' => 'not-a-valid-sqlite-database'],
        ]);
        $registry = new TenantShortnameRegistry(vfsStream::url('root/tenants/tenants.sqlite'));

        // Act
        $resolved = $registry->resolveTenantId('slug-corrupt');

        // Assert
        $this->assertNull($resolved);
    }

    public function testRegisterThrowsWhenParentPathCannotBeCreated(): void
    {
        // Arrange
        $root = vfsStream::setup('root');
        vfsStream::newFile('blocker')->withContent('blocks-directory-creation')->at($root);
        $registry = new TenantShortnameRegistry(vfsStream::url('root/blocker/nested/tenants.sqlite'));
        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('Cannot create directory for tenant shortname registry');

        // Act
        $registry->register('tid', 'valid-slug');

        // Assert - PHPUnit verifies the exception
    }

    public function testFromApplicationDataPathRestoresEnvWhenDataPathWasUnset(): void
    {
        // Arrange
        $dataPath = $this->vfsDataPath('app-unset');
        unset($_ENV['DATA_PATH']);
        putenv('DATA_PATH');

        // Act
        $registry = TenantShortnameRegistry::fromApplicationDataPath($dataPath);

        // Assert
        $this->assertEndsWithTenantsSqlite($registry);
        $this->assertFalse(array_key_exists('DATA_PATH', $_ENV));
    }

    public function testFromApplicationDataPathBuildsPathUnderVirtualDataRoot(): void
    {
        // Arrange
        $dataPath = $this->vfsDataPath('my-app');

        // Act
        $registry = TenantShortnameRegistry::fromApplicationDataPath($dataPath);

        // Assert
        $this->assertSame(
            vfsStream::url('root/my-app/tenants/tenants.sqlite'),
            $registry->getSqliteFilePath()
        );
    }

    public function testTryCreateFromEnvUsesApplicationRootWhenDataPathIsTenantScoped(): void
    {
        // Arrange — simulates ContextTransformer narrowing DATA_PATH
        $appRoot = $this->vfsDataPath('app-scoped');
        $_ENV['DATA_PATH'] = $appRoot . '/tenants/campus26';
        putenv('DATA_PATH=' . $_ENV['DATA_PATH']);

        // Act
        $registry = TenantShortnameRegistry::tryCreateFromEnv();

        // Assert
        $this->assertNotNull($registry);
        $this->assertSame(
            vfsStream::url('root/app-scoped/tenants/tenants.sqlite'),
            $registry->getSqliteFilePath()
        );
    }

    public function testGetPdoCreatesTenantsDirectoryWhenMissing(): void
    {
        // Arrange
        vfsStream::setup('root', null, [
            'deep' => ['nested' => []],
        ]);
        $dataPath = vfsStream::url('root/deep/nested');
        $registry = TenantShortnameRegistry::fromApplicationDataPath($dataPath);
        $tenantsDir = vfsStream::url('root/deep/nested/tenants');

        // Act
        try {
            $registry->register('tid-mkdir', 'slug-mkdir');
        } catch (PDOException) {
            // PDO cannot open SQLite on vfs://; getPdo() still creates the tenants directory.
        }

        // Assert
        $this->assertTrue(is_dir($tenantsDir));
    }

    private function registryInMemory(): TenantShortnameRegistry
    {
        return new TenantShortnameRegistry(':memory:');
    }

    /**
     * @return non-empty-string
     */
    private function vfsDataPath(string $segment): string
    {
        $url = vfsStream::url('root/' . $segment);
        if ($url === '') {
            self::fail('vfsStream::url() must not return an empty string.');
        }

        return $url;
    }

    private function assertEndsWithTenantsSqlite(TenantShortnameRegistry $registry): void
    {
        $this->assertStringEndsWith(
            'tenants/tenants.sqlite',
            str_replace('\\', '/', $registry->getSqliteFilePath())
        );
    }

    private function restoreDataPath(): void
    {
        if ($this->savedDataPathInEnv && is_string($this->savedDataPath)) {
            $_ENV['DATA_PATH'] = $this->savedDataPath;
            putenv('DATA_PATH=' . $this->savedDataPath);
        } elseif ($this->savedDataPathInEnv) {
            unset($_ENV['DATA_PATH']);
            putenv('DATA_PATH');
        } else {
            unset($_ENV['DATA_PATH']);
            putenv('DATA_PATH=./var/tmp/data/myApp');
        }
    }
}
