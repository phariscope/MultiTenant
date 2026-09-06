<?php

declare(strict_types=1);

namespace Phariscope\MultiTenant\Tests\Command;

use Phariscope\MultiTenant\Command\DeleteTenantCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
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
        $command = new DeleteTenantCommand();
        $output = $this->createMock(OutputInterface::class);
        $output->expects($this->once())
            ->method('writeln')
            ->with('<error>Provide --tenant_id.</error>');

        $exitCode = $command->run(new ArrayInput([]), $output);

        $this->assertSame(Command::FAILURE, $exitCode);
    }

    public function testExecuteFailsWhenTenantIdIsWhitespaceOnly(): void
    {
        $command = new DeleteTenantCommand();
        $output = $this->createMock(OutputInterface::class);
        $output->expects($this->once())
            ->method('writeln')
            ->with('<error>Provide --tenant_id.</error>');

        $exitCode = $command->run(new ArrayInput(['--tenant_id' => '   ']), $output);

        $this->assertSame(Command::FAILURE, $exitCode);
    }
}
