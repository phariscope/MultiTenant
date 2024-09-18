<?php

namespace Phariscope\MultiTenant\Tests\Commmand;

use Doctrine\ORM\EntityManager;
use Phariscope\MultiTenant\Command\CreateTenantSchemaCommand;
use Phariscope\MultiTenant\Doctrine\DatabaseTools;
use Phariscope\MultiTenant\Doctrine\EntityManagerResolver;
use Phariscope\MultiTenant\Tests\Doctrine\Tools\FakeEntityManagerFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Application;
use Symfony\Component\Filesystem\Filesystem;

class CreateTenantSchemaCommandTest extends TestCase
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
        $command = new CreateTenantSchemaCommand($this->em);
        $application->add($command);

        return new CommandTester($application->find('tenant:schema:create'));
    }

    public function testExecuteSuccess(): void
    {
        $tenantId = 'tenant123';
        $this->createDatabaseForTenant($tenantId);

        $this->commandTester->execute([
            'tenant_id' => $tenantId,
        ]);

        $this->assertConsoleSuccessOutput($tenantId);
    }

    private function createDatabaseForTenant(string $tenantId): void
    {
        $databaseTools = new DatabaseTools();
        $emResolver = new EntityManagerResolver($this->em);
        $emTenant = $emResolver->getEntityManager($tenantId);
        $databaseTools->createDatabase($emTenant);
    }

    private function assertConsoleSuccessOutput(string $expectedTenantId): void
    {
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString(
            'Database for tenant "' . $expectedTenantId . '" created successfully.',
            $output
        );
    }
}
