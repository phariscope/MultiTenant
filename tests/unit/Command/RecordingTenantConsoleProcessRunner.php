<?php

declare(strict_types=1);

namespace Phariscope\MultiTenant\Tests\Command;

use Phariscope\MultiTenant\Command\TenantConsoleProcessResult;
use Phariscope\MultiTenant\Command\TenantConsoleProcessRunnerInterface;

final class RecordingTenantConsoleProcessRunner implements TenantConsoleProcessRunnerInterface
{
    /** @var list<array{command: string, parameters: array<int|string, mixed>}> */
    public array $calls = [];

    public function __construct(
        private readonly int $exitCode = 0,
        private readonly string $output = '',
    ) {
    }

    /**
     * @param array<int|string, mixed> $parameters
     */
    public function run(string $commandName, array $parameters = []): TenantConsoleProcessResult
    {
        $this->calls[] = [
            'command' => $commandName,
            'parameters' => $parameters,
        ];

        return new TenantConsoleProcessResult($this->exitCode, $this->output);
    }
}
