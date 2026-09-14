<?php

declare(strict_types=1);

namespace Phariscope\MultiTenant\Tests\Command\Migrations;

use Phariscope\MultiTenant\Command\Migrations\MigrateTenantMigrationsCommand;
use Phariscope\MultiTenant\Command\Migrations\TenantMigrationDatabaseInspector;
use Phariscope\MultiTenant\Tests\Command\PreparesTenantSqliteFixture;
use Phariscope\MultiTenant\Tests\Command\RecordingTenantConsoleProcessRunner;
use Phariscope\MultiTenant\Tests\Share\IsolatesDataPathEnvTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class MigrateTenantMigrationsCommandTest extends TestCase
{
    use IsolatesDataPathEnvTrait;
    use PreparesTenantSqliteFixture;

    /** @var mixed */
    private $savedDatabaseUrl;

    protected function setUp(): void
    {
        parent::setUp();
        $this->savedDatabaseUrl = $_ENV['DATABASE_URL'] ?? null;
        unset($_ENV['DATABASE_URL']);
        $this->setUpIsolatedWritableDataPath();
    }

    protected function tearDown(): void
    {
        if ($this->savedDatabaseUrl === null) {
            unset($_ENV['DATABASE_URL']);
        } else {
            $_ENV['DATABASE_URL'] = $this->savedDatabaseUrl;
        }
        $this->tearDownIsolatedDataPath();
        parent::tearDown();
    }

    public function testMigrateDelegatesToDoctrineMigrationsMigrate(): void
    {
        // Arrange
        $this->createTenantSqliteDatabase('tenant-abc');
        $runner = new RecordingTenantConsoleProcessRunner(0, 'OK');
        $commandTester = $this->createCommandTester($runner);

        // Act
        $commandTester->execute([
            '--tenant_id' => 'tenant-abc',
        ]);

        // Assert
        $this->assertSame(0, $commandTester->getStatusCode());
        $this->assertStringContainsString('Migrations applied', $commandTester->getDisplay());
        $this->assertSame(
            [
                [
                    'command' => 'doctrine:migrations:migrate',
                    'parameters' => ['no-interaction' => true],
                ],
            ],
            $runner->calls
        );
    }

    public function testMigrateFailsWhenDatabaseFileIsMissing(): void
    {
        // Arrange
        $runner = new RecordingTenantConsoleProcessRunner();
        $commandTester = $this->createCommandTester($runner);

        // Act
        $commandTester->execute([
            '--tenant_id' => 'tenant-missing',
        ]);

        // Assert
        $this->assertSame(1, $commandTester->getStatusCode());
        $this->assertStringContainsString('database file not found', $commandTester->getDisplay());
        $this->assertSame([], $runner->calls);
    }

    private function createCommandTester(RecordingTenantConsoleProcessRunner $runner): CommandTester
    {
        $application = new Application();
        $application->add(new MigrateTenantMigrationsCommand(
            $runner,
            new TenantMigrationDatabaseInspector(),
        ));

        return new CommandTester($application->find('tenant:migrations:migrate'));
    }
}
