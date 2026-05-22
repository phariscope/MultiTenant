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

    public function testRegistersAndReturnsTenantIdWhenOnlyTenantIdProvided(): void
    {
        // Arrange
        $this->isolatedDataPathDir = sys_get_temp_dir() . '/mt-tcor-id-' . uniqid('', true);
        $this->setDataPathEnv($this->isolatedDataPathDir);
        $input = new ArrayInput(['--tenant_id' => 't1'], $this->definition());

        // Act
        $resolved = TenantConsoleOptionResolver::resolveTenantId($input);

        // Assert
        $this->assertSame('t1', $resolved);
        $registry = TenantShortnameRegistry::fromApplicationDataPath($this->isolatedDataPathDir);
        $this->assertSame('t1', $registry->resolveTenantId('t1'));
    }

    public function testRegistersMappingWhenBothOptionsProvided(): void
    {
        // Arrange
        $this->isolatedDataPathDir = sys_get_temp_dir() . '/mt-tcor-both-' . uniqid('', true);
        $this->setDataPathEnv($this->isolatedDataPathDir);
        $input = new ArrayInput([
            '--tenant_id' => 'real-tenant-id',
            '--tenant_shortname' => 'acme',
        ], $this->definition());

        // Act
        $resolved = TenantConsoleOptionResolver::resolveTenantId($input);

        // Assert
        $this->assertSame('real-tenant-id', $resolved);
        $registry = TenantShortnameRegistry::fromApplicationDataPath($this->isolatedDataPathDir);
        $this->assertSame('real-tenant-id', $registry->resolveTenantId('acme'));
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

    public function testRegistersInGlobalRegistryWhenDataPathIsTenantScoped(): void
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
        $resolved = TenantConsoleOptionResolver::resolveTenantId($input);

        // Assert
        $this->assertSame('campus26', $resolved);
        $registry = TenantShortnameRegistry::fromApplicationDataPath($this->isolatedDataPathDir);
        $this->assertSame('campus26', $registry->resolveTenantId('c26'));
        $this->assertFileDoesNotExist($scopedPath . '/tenants/tenants.sqlite');
    }

    public function testThrowsWhenDataPathMissing(): void
    {
        // Arrange
        $this->clearDataPathEnv();
        $input = new ArrayInput(['--tenant_id' => 't1'], $this->definition());
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DATA_PATH must be set to register tenant shortname mapping');

        // Act
        TenantConsoleOptionResolver::resolveTenantId($input);

        // Assert - PHPUnit verifies the exception
    }
}
