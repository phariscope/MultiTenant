<?php

declare(strict_types=1);

namespace Phariscope\MultiTenant\Tests\Command;

use Phariscope\MultiTenant\Command\ShowTenantShortnameCommand;
use Phariscope\MultiTenant\Infrastructure\TenantShortname\TenantShortnameRegistry;
use Phariscope\MultiTenant\Tests\Share\IsolatesDataPathEnvTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ShowTenantShortnameCommandTest extends TestCase
{
    use IsolatesDataPathEnvTrait;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->setUpIsolatedWritableDataPath();
        $dataPath = $this->isolatedDataPathDir;
        self::assertNotNull($dataPath);
        $registry = TenantShortnameRegistry::fromApplicationDataPath($dataPath);
        $registry->register('tid-abc', 'my-school');
        $this->commandTester = $this->createCommandTester();
    }

    protected function tearDown(): void
    {
        $this->tearDownIsolatedDataPath();
    }

    private function createCommandTester(): CommandTester
    {
        $application = new Application();
        $application->add(new ShowTenantShortnameCommand());

        return new CommandTester($application->find('tenant:shortname:show'));
    }

    public function testExecutePrintsShortnameForKnownTenant(): void
    {
        // Act
        $exitCode = $this->commandTester->execute([
            '--tenant_id' => 'tid-abc',
        ]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertSame("my-school\n", $this->commandTester->getDisplay());
    }

    public function testExecuteFailsWhenTenantIdUnknown(): void
    {
        // Act
        $exitCode = $this->commandTester->execute([
            '--tenant_id' => 'unknown-tenant',
        ]);

        // Assert
        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString(
            'No shortname registered for tenant "unknown-tenant".',
            $this->commandTester->getDisplay()
        );
    }

    public function testExecuteFailsWhenTenantIdMissing(): void
    {
        // Act
        $exitCode = $this->commandTester->execute([]);

        // Assert
        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Provide --tenant_id.', $this->commandTester->getDisplay());
    }

    public function testExecuteFailsWhenDataPathNotSet(): void
    {
        // Arrange
        $this->clearDataPathEnv();

        // Act
        $exitCode = $this->commandTester->execute([
            '--tenant_id' => 'tid-abc',
        ]);

        // Assert
        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString(
            'DATA_PATH must be set to read tenant shortname mapping from tenants/tenants.sqlite.',
            $this->commandTester->getDisplay()
        );
    }
}
