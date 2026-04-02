<?php

declare(strict_types=1);

namespace Phariscope\MultiTenant\Tests\Command;

use Doctrine\ORM\EntityManager;
use Phariscope\MultiTenant\Command\CreateTenantDatabaseCommand;
use Phariscope\MultiTenant\Command\UpdateTenantSchemaCommand;
use Phariscope\MultiTenant\Tests\Doctrine\Tools\FakeEntityManagerFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class UpdateTenantSchemaCommandTest extends TestCase
{
    private EntityManager $em;

    protected function setUp(): void
    {
        (new FakeEntityManagerFactory())->cleanSqliteDatabase();
        $this->em = (new FakeEntityManagerFactory())->createSqliteEntityManager();
    }

    protected function tearDown(): void
    {
        (new FakeEntityManagerFactory())->cleanSqliteDatabase();
    }

    public function testFailsWhenPendingChangesWithoutForceOrDumpSql(): void
    {
        // Arrange
        $tenantId = 'tenant123';
        $this->createDatabaseForTenant($tenantId);
        $commandTester = $this->createUpdateCommandTester();

        // Act
        $exitCode = $commandTester->execute([
            '--tenant_id' => $tenantId,
        ]);

        // Assert
        $this->assertSame(1, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('--force', $output);
        $this->assertStringContainsString('--dump-sql', $output);
    }

    private function createUpdateCommandTester(): CommandTester
    {
        $application = new Application();
        $application->add(new UpdateTenantSchemaCommand($this->em));

        return new CommandTester($application->find('tenant:schema:update'));
    }

    private function createDatabaseForTenant(string $tenantId): void
    {
        $application = new Application();
        $application->add(new CreateTenantDatabaseCommand($this->em));
        $commandTester = new CommandTester($application->find('tenant:database:create'));
        $commandTester->execute([
            '--tenant_id' => $tenantId,
        ]);
        $this->assertStringContainsString(
            'Database for tenant "' . $tenantId . '" created successfully.',
            $commandTester->getDisplay()
        );
    }

    public function testDumpSqlPrintsStatementsWithoutExecuting(): void
    {
        // Arrange
        $tenantId = 'tenant123';
        $this->createDatabaseForTenant($tenantId);
        $commandTester = $this->createUpdateCommandTester();

        // Act
        $exitCode = $commandTester->execute([
            '--tenant_id' => $tenantId,
            '--dump-sql' => true,
        ]);

        // Assert
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('CREATE TABLE', $commandTester->getDisplay());
    }

    public function testForceAppliesPendingSchemaChanges(): void
    {
        // Arrange
        $tenantId = 'tenant123';
        $this->createDatabaseForTenant($tenantId);
        $commandTester = $this->createUpdateCommandTester();

        // Act
        $exitCode = $commandTester->execute([
            '--tenant_id' => $tenantId,
            '--force' => true,
        ]);

        // Assert
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString(
            'Schema for tenant "' . $tenantId . '" updated successfully.',
            $commandTester->getDisplay()
        );

        $tables = $this->em->getConnection()->createSchemaManager()->listTableNames();
        $this->assertContains('entities', $tables);
    }

    public function testExecuteFailsWithClearMessageWhenUpdateCannotRun(): void
    {
        // Arrange
        $tenantId = 'tenant123';
        $commandTester = $this->createUpdateCommandTester();

        // Act
        $exitCode = $commandTester->execute([
            '--tenant_id' => $tenantId,
            '--force' => true,
        ]);

        // Assert
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString(
            'Could not update schema for tenant "' . $tenantId . '"',
            $commandTester->getDisplay()
        );
    }

    public function testDumpSqlAndForceTogether(): void
    {
        // Arrange
        $tenantId = 'tenant123';
        $this->createDatabaseForTenant($tenantId);
        $commandTester = $this->createUpdateCommandTester();

        // Act
        $exitCode = $commandTester->execute([
            '--tenant_id' => $tenantId,
            '--dump-sql' => true,
            '--force' => true,
        ]);

        // Assert
        $this->assertSame(0, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('CREATE TABLE', $output);
        $this->assertStringContainsString(
            'Schema for tenant "' . $tenantId . '" updated successfully.',
            $output
        );
    }
}
