<?php

namespace Phariscope\MultiTenant\Tests\Command;

use Doctrine\ORM\EntityManager;
use Phariscope\MultiTenant\Command\CreateTenantDatabaseCommand;
use Phariscope\MultiTenant\Doctrine\Tools\ParamsConnection;
use Phariscope\MultiTenant\Tests\Doctrine\Tools\FakeEntityManagerFactory;
use Phariscope\MultiTenant\Tests\Share\IsolatesDataPathEnvTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Application;
use Symfony\Component\Filesystem\Filesystem;

use function SafePHP\strval;

class CreateTenantDatabaseCommandTest extends TestCase
{
    use IsolatesDataPathEnvTrait;

    private CommandTester $commandTester;

    private EntityManager $em;

    protected function setUp(): void
    {
        (new FakeEntityManagerFactory())->cleanSqliteDatabase();
        $this->em = (new FakeEntityManagerFactory())->createSqliteEntityManager();
        $this->commandTester = $this->createCommandTester();
        $this->setUpIsolatedWritableDataPath();
    }

    protected function tearDown(): void
    {
        $this->tearDownIsolatedDataPath();
        (new FakeEntityManagerFactory())->cleanSqliteDatabase();
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
        // Arrange
        $tenantId = 'tenant123';

        // Act
        $this->commandTester->execute([
            '--tenant_id' => $tenantId,
            '--verbose' => 2,
        ]);

        // Assert
        $this->assertConsoleSuccessOutput($tenantId);
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
        // Arrange
        $tenantId = 'tenant123';
        $tenantDbPath = strval(ParamsConnection::getParam($this->em->getConnection()->getParams(), 'path'));

        $filesystem = new Filesystem();
        $filesystem->mkdir(dirname($tenantDbPath));
        touch($tenantDbPath);

        // Act
        $exitCode = $this->commandTester->execute([
            '--tenant_id' => $tenantId,
            '--verbose' => 2,
        ]);

        // Assert
        $this->assertSame(1, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringStartsWith('Could not create database for tenant "' . $tenantId . '"', $output);

        // clean up
        $filesystem->remove($tenantDbPath);
    }

    public function testExecuteFailureWhenTenantIdMissing(): void
    {
        // Arrange
        $saved = $this->captureDataPathEnv();
        $this->clearDataPathEnv();

        // Act
        $exitCode = $this->commandTester->execute([]);

        // Assert
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Provide --tenant_id', $this->commandTester->getDisplay());

        $this->restoreDataPathEnv($saved);
    }
}
