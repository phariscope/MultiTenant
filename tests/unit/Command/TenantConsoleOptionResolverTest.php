<?php

declare(strict_types=1);

namespace Phariscope\MultiTenant\Tests\Command;

use InvalidArgumentException;
use Phariscope\MultiTenant\Command\TenantConsoleOptionResolver;
use Phariscope\MultiTenant\Infrastructure\TenantShortname\TenantShortnameRegistry;
use Phariscope\MultiTenant\Tests\Share\IsolatesDataPathEnvTrait;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Filesystem\Filesystem;

class TenantConsoleOptionResolverTest extends TestCase
{
    use IsolatesDataPathEnvTrait;

    protected function setUp(): void
    {
        $this->setUpDataPathEnvSnapshot();
    }

    protected function tearDown(): void
    {
        if ($this->isolatedDataPathDir !== null && is_dir($this->isolatedDataPathDir)) {
            (new Filesystem())->remove($this->isolatedDataPathDir);
            $this->isolatedDataPathDir = null;
        }

        $this->tearDownDataPathEnvSnapshot();
    }

    private function definition(): InputDefinition
    {
        return new InputDefinition([
            new InputOption('tenant_id', null, InputOption::VALUE_OPTIONAL),
            new InputOption('tenant_shortname', null, InputOption::VALUE_OPTIONAL),
        ]);
    }

    public function testResolveTenantIdReturnsIdWithoutWritingRegistry(): void
    {
        // Arrange — fixtures ship demo → cl_demo_67mzxiq; migrate must not replace demo with tenant_id
        $this->isolatedDataPathDir = sys_get_temp_dir() . '/mt-tcor-no-reg-' . uniqid('', true);
        $this->setDataPathEnv($this->isolatedDataPathDir);
        $registry = $this->registryForIsolatedDataPath();
        $registry->register('cl_demo_67mzxiq', 'demo');
        $input = new ArrayInput(['--tenant_id' => 'cl_demo_67mzxiq'], $this->definition());

        // Act
        $resolved = TenantConsoleOptionResolver::resolveTenantId($input);

        // Assert
        $this->assertSame('cl_demo_67mzxiq', $resolved);
        $this->assertSame('cl_demo_67mzxiq', $registry->resolveTenantId('demo'));
        $this->assertNull($registry->resolveTenantId('cl_demo_67mzxiq'));
    }

    public function testResolveTenantIdSucceedsWithoutDataPath(): void
    {
        // Arrange
        $this->clearDataPathEnv();
        $input = new ArrayInput(['--tenant_id' => 't1'], $this->definition());

        // Act
        $resolved = TenantConsoleOptionResolver::resolveTenantId($input);

        // Assert
        $this->assertSame('t1', $resolved);
    }

    public function testResolveTenantIdIgnoresShortnameAndDoesNotWrite(): void
    {
        // Arrange
        $this->isolatedDataPathDir = sys_get_temp_dir() . '/mt-tcor-ignore-sn-' . uniqid('', true);
        $this->setDataPathEnv($this->isolatedDataPathDir);
        $input = new ArrayInput([
            '--tenant_id' => 'real-tenant-id',
            '--tenant_shortname' => 'acme',
        ], $this->definition());

        // Act
        $resolved = TenantConsoleOptionResolver::resolveTenantId($input);

        // Assert
        $this->assertSame('real-tenant-id', $resolved);
        $registry = $this->registryForIsolatedDataPath();
        $this->assertNull($registry->resolveTenantId('acme'));
        $this->assertNull($registry->resolveTenantId('real-tenant-id'));
    }

    public function testRegisterShortnameFromInputUsesTenantIdWhenShortnameOmitted(): void
    {
        // Arrange
        $this->isolatedDataPathDir = sys_get_temp_dir() . '/mt-tcor-id-' . uniqid('', true);
        $this->setDataPathEnv($this->isolatedDataPathDir);
        $input = new ArrayInput(['--tenant_id' => 't1'], $this->definition());

        // Act
        $registered = TenantConsoleOptionResolver::registerShortnameFromInput($input, 't1');

        // Assert
        $this->assertSame('t1', $registered);
        $registry = $this->registryForIsolatedDataPath();
        $this->assertSame('t1', $registry->resolveTenantId('t1'));
    }

    public function testRegisterShortnameFromInputRegistersMappingWhenBothOptionsProvided(): void
    {
        // Arrange
        $this->isolatedDataPathDir = sys_get_temp_dir() . '/mt-tcor-both-' . uniqid('', true);
        $this->setDataPathEnv($this->isolatedDataPathDir);
        $input = new ArrayInput([
            '--tenant_id' => 'real-tenant-id',
            '--tenant_shortname' => 'acme',
        ], $this->definition());

        // Act
        $registered = TenantConsoleOptionResolver::registerShortnameFromInput($input, 'real-tenant-id');

        // Assert
        $this->assertSame('acme', $registered);
        $registry = $this->registryForIsolatedDataPath();
        $this->assertSame('real-tenant-id', $registry->resolveTenantId('acme'));
    }

    public function testRegisterShortnameFromInputAllocatesUniqueSuffixWhenShortnameTaken(): void
    {
        // Arrange
        $this->isolatedDataPathDir = sys_get_temp_dir() . '/mt-tcor-collision-' . uniqid('', true);
        $this->setDataPathEnv($this->isolatedDataPathDir);
        $registry = $this->registryForIsolatedDataPath();
        $registry->register('other-tenant', 'acme');
        $input = new ArrayInput([
            '--tenant_id' => 'new-tenant-id',
            '--tenant_shortname' => 'acme',
        ], $this->definition());

        // Act
        $registered = TenantConsoleOptionResolver::registerShortnameFromInput($input, 'new-tenant-id');

        // Assert
        $this->assertSame('acme-2', $registered);
        $this->assertSame('new-tenant-id', $registry->resolveTenantId('acme-2'));
    }

    public function testRejectsNeitherOption(): void
    {
        // Arrange
        $input = new ArrayInput([], $this->definition());
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Provide --tenant_id.');

        // Act
        TenantConsoleOptionResolver::resolveTenantId($input);

        // Assert - PHPUnit verifies the exception
    }

    public function testRejectsShortnameWithoutTenantId(): void
    {
        // Arrange
        $input = new ArrayInput(['--tenant_shortname' => 'acme'], $this->definition());
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('--tenant_shortname requires --tenant_id.');

        // Act
        TenantConsoleOptionResolver::resolveTenantId($input);

        // Assert - PHPUnit verifies the exception
    }

    public function testRegisterShortnameFromInputUsesGlobalRegistryWhenDataPathIsTenantScoped(): void
    {
        // Arrange
        $this->isolatedDataPathDir = sys_get_temp_dir() . '/mt-tcor-scoped-root-' . uniqid('', true);
        $scopedPath = $this->isolatedDataPathDir . '/tenants/campus26';
        $this->setDataPathEnv($scopedPath);
        $input = new ArrayInput([
            '--tenant_id' => 'campus26',
            '--tenant_shortname' => 'c26',
        ], $this->definition());

        // Act
        $registered = TenantConsoleOptionResolver::registerShortnameFromInput($input, 'campus26');

        // Assert
        $this->assertSame('c26', $registered);
        $registry = $this->registryForIsolatedDataPath();
        $this->assertSame('campus26', $registry->resolveTenantId('c26'));
        $this->assertFileDoesNotExist($scopedPath . '/tenants/tenants.sqlite');
    }

    public function testRegisterShortnameFromInputThrowsWhenDataPathMissing(): void
    {
        // Arrange
        $this->clearDataPathEnv();
        $input = new ArrayInput(['--tenant_id' => 't1'], $this->definition());
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DATA_PATH must be set to register tenant shortname mapping');

        // Act
        TenantConsoleOptionResolver::registerShortnameFromInput($input, 't1');

        // Assert - PHPUnit verifies the exception
    }

    private function registryForIsolatedDataPath(): TenantShortnameRegistry
    {
        $dataPath = $this->isolatedDataPathDir;
        self::assertNotNull($dataPath);

        return TenantShortnameRegistry::fromApplicationDataPath($dataPath);
    }
}
