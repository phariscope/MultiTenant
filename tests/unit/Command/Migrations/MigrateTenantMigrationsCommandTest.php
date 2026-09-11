<?php

declare(strict_types=1);

namespace Phariscope\MultiTenant\Tests\Command\Migrations;

use Phariscope\MultiTenant\Command\Migrations\MigrateTenantMigrationsCommand;
use Phariscope\MultiTenant\Command\Migrations\TenantMigrationDatabaseInspector;
use Phariscope\MultiTenant\Command\TenantConsoleProcessResult;
use Phariscope\MultiTenant\Command\TenantConsoleProcessRunnerInterface;
use Phariscope\MultiTenant\Tests\Share\IsolatesDataPathEnvTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class MigrateTenantMigrationsCommandTest extends TestCase
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

    public function testMigrateDelegatesToDoctrineMigrationsMigrate(): void
    {
        // Arrange
        $runner = $this->createMock(TenantConsoleProcessRunnerInterface::class);
        $runner->expects($this->once())
            ->method('run')
            ->with(
                'doctrine:migrations:migrate',
                [
                    '--no-interaction' => true,
                    '--tenant_id' => 'tenant-abc',
                ]
            )
            ->willReturn(new TenantConsoleProcessResult(0, 'OK'));

        $inspector = $this->createMock(TenantMigrationDatabaseInspector::class);
        $inspector->method('tenantDatabaseExists')->willReturn(true);

        $commandTester = $this->createCommandTester(new MigrateTenantMigrationsCommand($runner, $inspector));

        // Act
        $commandTester->execute([
            '--tenant_id' => 'tenant-abc',
        ]);

        // Assert
        $this->assertSame(0, $commandTester->getStatusCode());
        $this->assertStringContainsString('Migrations applied', $commandTester->getDisplay());
    }

    public function testMigrateFailsWhenDatabaseFileIsMissing(): void
    {
        // Arrange
        $runner = $this->createMock(TenantConsoleProcessRunnerInterface::class);
        $runner->expects($this->never())->method('run');

        $inspector = $this->createMock(TenantMigrationDatabaseInspector::class);
        $inspector->method('tenantDatabaseExists')->willReturn(false);

        $commandTester = $this->createCommandTester(new MigrateTenantMigrationsCommand($runner, $inspector));

        // Act
        $commandTester->execute([
            '--tenant_id' => 'tenant-missing',
        ]);

        // Assert
        $this->assertSame(1, $commandTester->getStatusCode());
        $this->assertStringContainsString('database file not found', $commandTester->getDisplay());
    }

    private function createCommandTester(MigrateTenantMigrationsCommand $command): CommandTester
    {
        $application = new Application();
        $application->add($command);

        return new CommandTester($application->find('tenant:migrations:migrate'));
    }
}
