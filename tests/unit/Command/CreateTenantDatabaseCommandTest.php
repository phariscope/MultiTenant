<?php

namespace Phariscope\MultiTenant\Tests\Commmand;

use Doctrine\ORM\EntityManager;
use Phariscope\MultiTenant\Command\CreateTenantDatabaseCommand;
use Phariscope\MultiTenant\Tests\Doctrine\FakeEntityManagerFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Application;
use Symfony\Component\Filesystem\Filesystem;

class CreateTenantDatabaseCommandTest extends TestCase
{
    private CommandTester $commandTester;

    private EntityManager $em;

    protected function setUp(): void
    {
        (new FakeEntityManagerFactory())->cleanSqliteDatabase();
        $this->em = (new FakeEntityManagerFactory())->createSqliteEntityManager();
        $this->commandTester = $this->createCommandTester();
    }

    private function createCommandTester(): CommandTester
    {
        $application = new Application();
        $command = new CreateTenantDatabaseCommand($this->em);
        $application->add($command);

        return new CommandTester($application->find('tenant:database:create'));
    }

    public function testExecuteSuccess(): void
    {

        $tenantId = 'tenant123';

        $this->commandTester->execute([
            'tenant_id' => $tenantId,
        ]);

        $this->assertConsoleSuccessOutput($tenantId);

        // Vérifier si la base de données a bien été créée
        $dbPath = getcwd() . '/var/tmp/data/databases/' . $tenantId . '/database.sqlite';
        $this->assertTrue(file_exists($dbPath));
    }

    private function assertConsoleSuccessOutput(string $expectedTenantId): void
    {
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString(
            'Database for tenant "' . $expectedTenantId . '" created successfully.',
            $output
        );
    }

    public function testExecuteFailureDatabaseAlreadyExists(): void
    {
        $tenantId = 'tenant123';
        $tenantDbPath = getcwd() . '/var/tmp/data/databases/' . $tenantId . '/database.sqlite';

        $filesystem = new Filesystem();
        $filesystem->mkdir(dirname($tenantDbPath));
        touch($tenantDbPath);

        $this->commandTester->execute([
            'tenant_id' => $tenantId,
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringStartsWith('Could not create database for tenant "' . $tenantId . '"', $output);
    }
}
