<?php

declare(strict_types=1);

namespace Phariscope\MultiTenant\Tests\Command;

use Phariscope\MultiTenant\Command\TenantConsoleProcessRunner;
use PHPUnit\Framework\TestCase;

final class TenantConsoleProcessRunnerTest extends TestCase
{
    public function testBuildCommandPrefixesBareOptionNames(): void
    {
        // Arrange
        $runner = new TenantConsoleProcessRunner('/app', 'php');

        // Act
        $command = $runner->buildCommand('doctrine:migrations:migrate', [
            'no-interaction' => true,
            'tenant_id' => 'tenant-abc',
        ]);

        // Assert
        $this->assertSame(
            [
                'php',
                '/app/bin/console',
                'doctrine:migrations:migrate',
                '--no-interaction',
                '--tenant_id=tenant-abc',
            ],
            $command
        );
    }

    public function testBuildCommandDoesNotDoubleDashOptionKeys(): void
    {
        // Arrange
        $runner = new TenantConsoleProcessRunner('/app', 'php');

        // Act
        $command = $runner->buildCommand('tenant:migrations:migrate', [
            '--tenant_id' => 'cl_demo10_yve5d6q',
        ]);

        // Assert
        $this->assertSame(
            [
                'php',
                '/app/bin/console',
                'tenant:migrations:migrate',
                '--tenant_id=cl_demo10_yve5d6q',
            ],
            $command
        );
        $this->assertNotContains('----tenant_id=cl_demo10_yve5d6q', $command);
    }
}
