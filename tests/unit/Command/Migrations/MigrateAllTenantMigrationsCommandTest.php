<?php

declare(strict_types=1);

namespace Phariscope\MultiTenant\Tests\Command\Migrations;

use Phariscope\MultiTenant\Command\Migrations\MigrateAllTenantMigrationsCommand;
use Phariscope\MultiTenant\Infrastructure\TenantShortname\TenantShortnameRegistry;
use Phariscope\MultiTenant\Tests\Command\RecordingTenantConsoleProcessRunner;
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

        $runner = new RecordingTenantConsoleProcessRunner();
        $commandTester = $this->createCommandTester($runner);

        // Act
        $commandTester->execute([]);

        // Assert
        $this->assertSame(0, $commandTester->getStatusCode());
        $this->assertStringContainsString('Successfully processed 2 tenant(s)', $commandTester->getDisplay());
        $this->assertCount(2, $runner->calls);
        $this->assertSame('tenant:migrations:migrate', $runner->calls[0]['command']);
        $this->assertSame('tenant:migrations:migrate', $runner->calls[1]['command']);
        $this->assertSame(
            ['tenant-a', 'tenant-b'],
            array_map(
                static fn (array $call): string => (string) $call['parameters']['tenant_id'],
                $runner->calls
            )
        );
    }

    public function testMigrateAllDryRunUsesStatusCommand(): void
    {
        // Arrange
        $registry = TenantShortnameRegistry::fromApplicationDataPath($this->getIsolatedDataPathDir());
        $registry->register('tenant-a', 'tenant-a');

        $runner = new RecordingTenantConsoleProcessRunner(0, 'status');
        $commandTester = $this->createCommandTester($runner);

        // Act
        $commandTester->execute(['--dry-run' => true]);

        // Assert
        $this->assertSame(0, $commandTester->getStatusCode());
        $this->assertStringContainsString('status', $commandTester->getDisplay());
        $this->assertSame(
            [
                [
                    'command' => 'tenant:migrations:status',
                    'parameters' => ['tenant_id' => 'tenant-a'],
                ],
            ],
            $runner->calls
        );
    }

    public function testMigrateAllStopsOnFirstFailure(): void
    {
        // Arrange
        $registry = TenantShortnameRegistry::fromApplicationDataPath($this->getIsolatedDataPathDir());
        $registry->register('tenant-a', 'tenant-a');
        $registry->register('tenant-b', 'tenant-b');

        $runner = new RecordingTenantConsoleProcessRunner(1, 'error');
        $commandTester = $this->createCommandTester($runner);

        // Act
        $commandTester->execute([]);

        // Assert
        $this->assertSame(1, $commandTester->getStatusCode());
        $this->assertStringContainsString('Stopped after failure', $commandTester->getDisplay());
        $this->assertCount(1, $runner->calls);
    }

    private function createCommandTester(RecordingTenantConsoleProcessRunner $runner): CommandTester
    {
        $application = new Application();
        $application->add(new MigrateAllTenantMigrationsCommand($runner));

        return new CommandTester($application->find('tenant:migrations:migrate-all'));
    }
}
