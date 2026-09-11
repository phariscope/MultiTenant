<?php

declare(strict_types=1);

namespace Phariscope\MultiTenant\Tests\Command\Migrations;

use org\bovigo\vfs\vfsStream;
use Phariscope\MultiTenant\Command\Migrations\MigrateAllTenantMigrationsCommand;
use Phariscope\MultiTenant\Command\TenantConsoleProcessResult;
use Phariscope\MultiTenant\Command\TenantConsoleProcessRunnerInterface;
use Phariscope\MultiTenant\Infrastructure\TenantShortname\TenantShortnameRegistry;
use Phariscope\MultiTenant\Tests\Share\IsolatesDataPathEnvTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class MigrateAllTenantMigrationsCommandTest extends TestCase
{
    use IsolatesDataPathEnvTrait;

    protected function setUp(): void
    {
        parent::setUp();
        vfsStream::setup('root');
        $this->setUpIsolatedWritableDataPath();
    }

    protected function tearDown(): void
    {
        $this->tearDownIsolatedDataPath();
        parent::tearDown();
    }

    public function testMigrateAllRunsMigrateForEachRegisteredTenant(): void
    {
        // Arrange
        $registry = TenantShortnameRegistry::fromApplicationDataPath($this->getIsolatedDataPathDir());
        $registry->register('tenant-a', 'tenant-a');
        $registry->register('tenant-b', 'tenant-b');

        $runner = $this->createMock(TenantConsoleProcessRunnerInterface::class);
        $runner->expects($this->exactly(2))
            ->method('run')
            ->willReturnCallback(function (string $commandName, array $parameters): TenantConsoleProcessResult {
                $this->assertSame('tenant:migrations:migrate', $commandName);
                $this->assertArrayHasKey('--tenant_id', $parameters);

                return new TenantConsoleProcessResult(0, '');
            });

        $commandTester = $this->createCommandTester(new MigrateAllTenantMigrationsCommand($runner));

        // Act
        $commandTester->execute([]);

        // Assert
        $this->assertSame(0, $commandTester->getStatusCode());
        $this->assertStringContainsString('Successfully processed 2 tenant(s)', $commandTester->getDisplay());
    }

    public function testMigrateAllDryRunUsesStatusCommand(): void
    {
        // Arrange
        $registry = TenantShortnameRegistry::fromApplicationDataPath($this->getIsolatedDataPathDir());
        $registry->register('tenant-a', 'tenant-a');

        $runner = $this->createMock(TenantConsoleProcessRunnerInterface::class);
        $runner->expects($this->once())
            ->method('run')
            ->with(
                'tenant:migrations:status',
                ['--tenant_id' => 'tenant-a']
            )
            ->willReturn(new TenantConsoleProcessResult(0, 'status'));

        $commandTester = $this->createCommandTester(new MigrateAllTenantMigrationsCommand($runner));

        // Act
        $commandTester->execute(['--dry-run' => true]);

        // Assert
        $this->assertSame(0, $commandTester->getStatusCode());
        $this->assertStringContainsString('status', $commandTester->getDisplay());
    }

    public function testMigrateAllStopsOnFirstFailure(): void
    {
        // Arrange
        $registry = TenantShortnameRegistry::fromApplicationDataPath($this->getIsolatedDataPathDir());
        $registry->register('tenant-a', 'tenant-a');
        $registry->register('tenant-b', 'tenant-b');

        $runner = $this->createMock(TenantConsoleProcessRunnerInterface::class);
        $runner->expects($this->once())
            ->method('run')
            ->willReturn(new TenantConsoleProcessResult(1, 'error'));

        $commandTester = $this->createCommandTester(new MigrateAllTenantMigrationsCommand($runner));

        // Act
        $commandTester->execute([]);

        // Assert
        $this->assertSame(1, $commandTester->getStatusCode());
        $this->assertStringContainsString('Stopped after failure', $commandTester->getDisplay());
    }

    private function createCommandTester(MigrateAllTenantMigrationsCommand $command): CommandTester
    {
        $application = new Application();
        $application->add($command);

        return new CommandTester($application->find('tenant:migrations:migrate-all'));
    }
}
