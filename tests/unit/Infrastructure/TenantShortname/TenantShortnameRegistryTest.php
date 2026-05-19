<?php

declare(strict_types=1);

namespace Phariscope\MultiTenant\Tests\Infrastructure\TenantShortname;

use InvalidArgumentException;
use Phariscope\MultiTenant\Infrastructure\TenantShortname\TenantShortnameRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class TenantShortnameRegistryTest extends TestCase
{
    private string $tmpBase = '';

    protected function tearDown(): void
    {
        if ($this->tmpBase !== '') {
            (new Filesystem())->remove($this->tmpBase);
            $this->tmpBase = '';
        }
    }

    public function testResolveSqliteFilePathForHostDataPath(): void
    {
        // Arrange
        $hostDataPath = '/var/app/data';

        // Act
        $path = TenantShortnameRegistry::resolveSqliteFilePath($hostDataPath);

        // Assert
        $this->assertSame('/var/app/data/tenants/tenants.sqlite', $path);
    }

    public function testResolveSqliteFilePathWhenDataPathIsTenantFolder(): void
    {
        // Arrange
        $tenantDataPath = '/var/app/data/tenants/t1';

        // Act
        $path = TenantShortnameRegistry::resolveSqliteFilePath($tenantDataPath);

        // Assert
        $this->assertSame('/var/app/data/tenants/tenants.sqlite', $path);
    }

    public function testRegisterAndResolve(): void
    {
        // Arrange
        $this->tmpBase = sys_get_temp_dir() . '/mt-reg-' . uniqid('', true);
        $registry = TenantShortnameRegistry::fromApplicationDataPath($this->tmpBase);
        $registry->register('tid-abc', 'My-Brand');

        // Act
        $resolved = $registry->resolveTenantId('my-brand');

        // Assert
        $this->assertSame('tid-abc', $resolved);
    }

    public function testRegisterReplacesShortnameForSameTenant(): void
    {
        // Arrange
        $this->tmpBase = sys_get_temp_dir() . '/mt-reg2-' . uniqid('', true);
        $registry = TenantShortnameRegistry::fromApplicationDataPath($this->tmpBase);
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
        $this->tmpBase = sys_get_temp_dir() . '/mt-reg3-' . uniqid('', true);
        $registry = TenantShortnameRegistry::fromApplicationDataPath($this->tmpBase);
        $this->expectException(InvalidArgumentException::class);

        // Act
        $registry->register('tid', 'Bad_Underscore');

        // Assert - PHPUnit verifies the exception
    }

    public function testRegisterAllowsTenantIdAsShortnameWhenEqual(): void
    {
        $this->tmpBase = sys_get_temp_dir() . '/mt-reg-id-slug-' . uniqid('', true);
        $registry = TenantShortnameRegistry::fromApplicationDataPath($this->tmpBase);

        $registry->register('am_cl_fixed', 'am_cl_fixed');

        $this->assertSame('am_cl_fixed', $registry->resolveTenantId('am_cl_fixed'));
    }

    public function testTryCreateFromEnvUsesGetenvWhenNotInSuperglobal(): void
    {
        // Arrange
        $tmp = sys_get_temp_dir() . '/mt-reg-env-' . uniqid('', true);
        $hadKey = array_key_exists('DATA_PATH', $_ENV);
        $previous = $hadKey ? $_ENV['DATA_PATH'] : null;
        unset($_ENV['DATA_PATH']);
        putenv('DATA_PATH=' . $tmp);

        // Act
        $registry = TenantShortnameRegistry::tryCreateFromEnv();

        // Assert
        $this->assertNotNull($registry);
        $this->assertStringEndsWith('tenants/tenants.sqlite', str_replace('\\', '/', $registry->getSqliteFilePath()));

        putenv('DATA_PATH');
        if ($hadKey && is_string($previous)) {
            $_ENV['DATA_PATH'] = $previous;
            putenv('DATA_PATH=' . $previous);
        } else {
            putenv('DATA_PATH');
        }
    }

    public function testTryCreateFromEnvReturnsNullWhenDataPathIsEmptyString(): void
    {
        // Arrange
        $hadKey = array_key_exists('DATA_PATH', $_ENV);
        $previous = $hadKey ? $_ENV['DATA_PATH'] : null;
        $_ENV['DATA_PATH'] = '';
        putenv('DATA_PATH=');

        // Act
        $registry = TenantShortnameRegistry::tryCreateFromEnv();

        // Assert
        $this->assertNull($registry);

        if ($hadKey && is_string($previous)) {
            $_ENV['DATA_PATH'] = $previous;
            putenv('DATA_PATH=' . $previous);
        } else {
            unset($_ENV['DATA_PATH']);
            putenv('DATA_PATH');
        }
    }

    public function testResolveSqliteFilePathNormalizesBackslashes(): void
    {
        // Arrange
        $mixed = 'C:\\app\\data\\tenants\\tid';

        // Act
        $path = TenantShortnameRegistry::resolveSqliteFilePath($mixed);

        // Assert
        $this->assertSame('C:/app/data/tenants/tenants.sqlite', $path);
    }

    public function testResolveReturnsNullForWhitespaceOnlyShortname(): void
    {
        // Arrange
        $this->tmpBase = sys_get_temp_dir() . '/mt-reg-ws-' . uniqid('', true);
        $registry = TenantShortnameRegistry::fromApplicationDataPath($this->tmpBase);

        // Act
        $resolved = $registry->resolveTenantId('   ');

        // Assert
        $this->assertNull($resolved);
    }
}
