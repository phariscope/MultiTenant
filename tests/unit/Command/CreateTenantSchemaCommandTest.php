<?php

namespace Phariscope\MultiTenant\Tests\Commmand;

use Doctrine\ORM\EntityManager;
use Phariscope\MultiTenant\Command\CreateTenantDatabaseCommand;
use Phariscope\MultiTenant\Command\CreateTenantSchemaCommand;
use Phariscope\MultiTenant\Doctrine\DatabaseTools;
use Phariscope\MultiTenant\Doctrine\EntityManagerResolver;
use Phariscope\MultiTenant\Tests\Doctrine\Tools\FakeEntityManagerFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Application;

class CreateTenantSchemaCommandTest extends TestCase
{
    private CommandTester $commandTester;

    private EntityManager $em;

    private string $initialDataPath;

    protected function setUp(): void
    {
        (new FakeEntityManagerFactory())->cleanSqliteDatabase();
        $this->em = (new FakeEntityManagerFactory())->createSqliteEntityManager();
        $this->commandTester = $this->createCommandTester();

        if (isset($_ENV['DATA_PATH'])) {
            $this->initialDataPath = $_ENV['DATA_PATH'];
            unset($_ENV['DATA_PATH']);
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->initialDataPath)) {
            $_ENV['DATA_PATH'] = $this->initialDataPath;
        } else {
            unset($_ENV['DATA_PATH']);
        }
    }

    private function createCommandTester(): CommandTester
    {
        $application = new Application();
        $command = new CreateTenantSchemaCommand($this->em);
        $application->add($command);

        return new CommandTester($application->find('tenant:schema:create'));
    }

    public function testExecuteSuccess(): void
    {
        // Arrange
        $tenantId = 'tenant123';
        $this->createDatabaseForTenant($tenantId);

        // Act
        $this->commandTester->execute(
            [
                'command' => 'cl:indicator:initialize',
                '--tenant_id' => $tenantId,
            ]
        );

        // Assert
        $this->assertConsoleSuccessOutput($tenantId);
    }

    private function createDatabaseForTenant(string $tenantId): void
    {
        $application = new Application();
        $command = new CreateTenantDatabaseCommand($this->em);
        $application->add($command);
        $commandTester = new CommandTester($application->find('tenant:database:create'));
        $commandTester->execute([
            '--tenant_id' => $tenantId,
        ]);

        $output = $commandTester->getDisplay();

        $this->assertStringContainsString(
            'Database for tenant "' . $tenantId . '" created successfully.',
            $output
        );
    }

    private function assertConsoleSuccessOutput(string $expectedTenantId): void
    {
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString(
            'Schema for tenant "' . $expectedTenantId . '" created successfully.',
            $output
        );
    }

    public function testSchemaAlreadyExists(): void
    {
        // Arrange
        $tenantId = 'tenant123';
        $this->createDatabaseForTenant($tenantId);

        $this->commandTester->execute([
            '--tenant_id' => $tenantId,
        ]);

        // Act
        $this->commandTester->execute([
            '--tenant_id' => $tenantId,
        ]);

        // Assert
        $this->assertConsoleFailureOutput($tenantId);
    }

    private function assertConsoleFailureOutput(string $expectedTenantId): void
    {
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString(
            'Could not create schema for tenant "' . $expectedTenantId,
            $output
        );
    }
}
