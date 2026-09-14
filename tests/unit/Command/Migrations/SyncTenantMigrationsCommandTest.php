<?php

declare(strict_types=1);

namespace Phariscope\MultiTenant\Tests\Command\Migrations;

use Phariscope\MultiTenant\Command\Migrations\SyncTenantMigrationsCommand;
use Phariscope\MultiTenant\Command\Migrations\TenantMigrationDatabaseInspector;
use Phariscope\MultiTenant\Tests\Command\PreparesTenantSqliteFixture;
use Phariscope\MultiTenant\Tests\Command\RecordingTenantConsoleProcessRunner;
use Phariscope\MultiTenant\Tests\Share\IsolatesDataPathEnvTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class SyncTenantMigrationsCommandTest extends TestCase
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

    public function testSyncDelegatesToDoctrineMigrationsVersion(): void
    {
        // Arrange
        $this->createTenantSqliteDatabase('tenant-abc');
        $runner = new RecordingTenantConsoleProcessRunner();
        $commandTester = $this->createCommandTester($runner);

        // Act
        $commandTester->execute([
            '--tenant_id' => 'tenant-abc',
        ]);

        // Assert
        $this->assertSame(0, $commandTester->getStatusCode());
        $this->assertStringContainsString('Migration history synced', $commandTester->getDisplay());
        $this->assertSame(
            [
                [
                    'command' => 'doctrine:migrations:version',
                    'parameters' => [
                        'add' => true,
                        'all' => true,
                        'no-interaction' => true,
                    ],
                ],
            ],
            $runner->calls
        );
    }

    public function testSyncRefusesWhenHistoryIsNotEmpty(): void
    {
        // Arrange
        $this->createTenantSqliteDatabaseWithMigrationHistory('tenant-abc', 2);
        $runner = new RecordingTenantConsoleProcessRunner();
        $commandTester = $this->createCommandTester($runner);

        // Act
        $commandTester->execute([
            '--tenant_id' => 'tenant-abc',
        ]);

        // Assert
        $this->assertSame(1, $commandTester->getStatusCode());
        $this->assertStringContainsString('not empty', $commandTester->getDisplay());
        $this->assertSame([], $runner->calls);
    }

    public function testSyncAllowsForceWhenHistoryIsNotEmpty(): void
    {
        // Arrange
        $this->createTenantSqliteDatabaseWithMigrationHistory('tenant-abc', 1);
        $runner = new RecordingTenantConsoleProcessRunner();
        $commandTester = $this->createCommandTester($runner);

        // Act
        $commandTester->execute([
            '--tenant_id' => 'tenant-abc',
            '--force' => true,
        ]);

        // Assert
        $this->assertSame(0, $commandTester->getStatusCode());
        $this->assertCount(1, $runner->calls);
        $this->assertSame('doctrine:migrations:version', $runner->calls[0]['command']);
    }

    private function createCommandTester(RecordingTenantConsoleProcessRunner $runner): CommandTester
    {
        $application = new Application();
        $application->add(new SyncTenantMigrationsCommand(
            $runner,
            new TenantMigrationDatabaseInspector(),
        ));

        return new CommandTester($application->find('tenant:migrations:sync'));
    }
}
