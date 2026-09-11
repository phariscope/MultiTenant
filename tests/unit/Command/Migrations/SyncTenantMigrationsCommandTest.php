<?php

declare(strict_types=1);

namespace Phariscope\MultiTenant\Tests\Command\Migrations;

use Phariscope\MultiTenant\Command\Migrations\SyncTenantMigrationsCommand;
use Phariscope\MultiTenant\Command\Migrations\TenantMigrationDatabaseInspector;
use Phariscope\MultiTenant\Command\TenantConsoleProcessResult;
use Phariscope\MultiTenant\Command\TenantConsoleProcessRunnerInterface;
use Phariscope\MultiTenant\Tests\Share\IsolatesDataPathEnvTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class SyncTenantMigrationsCommandTest extends TestCase
{
    use IsolatesDataPathEnvTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpIsolatedWritableDataPath();
    }

    protected function tearDown(): void
    {
        $this->tearDownIsolatedDataPath();
        parent::tearDown();
    }

    public function testSyncDelegatesToDoctrineMigrationsVersion(): void
    {
        // Arrange
        $runner = $this->createMock(TenantConsoleProcessRunnerInterface::class);
        $runner->expects($this->once())
            ->method('run')
            ->with(
                'doctrine:migrations:version',
                [
                    '--add' => true,
                    '--all' => true,
                    '--no-interaction' => true,
                    '--tenant_id' => 'tenant-abc',
                ]
            )
            ->willReturn(new TenantConsoleProcessResult(0, ''));

        $inspector = $this->createMock(TenantMigrationDatabaseInspector::class);
        $inspector->method('tenantDatabaseExists')->willReturn(true);
        $inspector->method('countExecutedMigrations')->willReturn(0);

        $commandTester = $this->createCommandTester(new SyncTenantMigrationsCommand($runner, $inspector));

        // Act
        $commandTester->execute([
            '--tenant_id' => 'tenant-abc',
        ]);

        // Assert
        $this->assertSame(0, $commandTester->getStatusCode());
        $this->assertStringContainsString('Migration history synced', $commandTester->getDisplay());
    }

    public function testSyncRefusesWhenHistoryIsNotEmpty(): void
    {
        // Arrange
        $runner = $this->createMock(TenantConsoleProcessRunnerInterface::class);
        $runner->expects($this->never())->method('run');

        $inspector = $this->createMock(TenantMigrationDatabaseInspector::class);
        $inspector->method('tenantDatabaseExists')->willReturn(true);
        $inspector->method('countExecutedMigrations')->willReturn(2);

        $commandTester = $this->createCommandTester(new SyncTenantMigrationsCommand($runner, $inspector));

        // Act
        $commandTester->execute([
            '--tenant_id' => 'tenant-abc',
        ]);

        // Assert
        $this->assertSame(1, $commandTester->getStatusCode());
        $this->assertStringContainsString('not empty', $commandTester->getDisplay());
    }

    public function testSyncAllowsForceWhenHistoryIsNotEmpty(): void
    {
        // Arrange
        $runner = $this->createMock(TenantConsoleProcessRunnerInterface::class);
        $runner->expects($this->once())
            ->method('run')
            ->willReturn(new TenantConsoleProcessResult(0, ''));

        $inspector = $this->createMock(TenantMigrationDatabaseInspector::class);
        $inspector->method('tenantDatabaseExists')->willReturn(true);
        $inspector->method('countExecutedMigrations')->willReturn(1);

        $commandTester = $this->createCommandTester(new SyncTenantMigrationsCommand($runner, $inspector));

        // Act
        $commandTester->execute([
            '--tenant_id' => 'tenant-abc',
            '--force' => true,
        ]);

        // Assert
        $this->assertSame(0, $commandTester->getStatusCode());
    }

    private function createCommandTester(SyncTenantMigrationsCommand $command): CommandTester
    {
        $application = new Application();
        $application->add($command);

        return new CommandTester($application->find('tenant:migrations:sync'));
    }
}
