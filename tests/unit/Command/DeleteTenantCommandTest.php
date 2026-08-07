<?php

declare(strict_types=1);

namespace Phariscope\MultiTenant\Tests\Command;

use Phariscope\MultiTenant\Command\DeleteTenantCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class DeleteTenantCommandTest extends TestCase
{
    public function testConfigureExposesTenantIdOption(): void
    {
        $command = new DeleteTenantCommand();
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('tenant_id'));
        $this->assertSame('tenant:delete', $command->getName());
    }

    public function testExecuteFailsWhenTenantIdMissing(): void
    {
        $tester = new CommandTester(new DeleteTenantCommand());
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('tenant_id', $tester->getDisplay());
    }
}
