<?php

declare(strict_types=1);

namespace Phariscope\MultiTenant\Command;

interface TenantConsoleProcessRunnerInterface
{
    /**
     * @param array<int|string, mixed> $parameters
     */
    public function run(string $commandName, array $parameters = []): TenantConsoleProcessResult;
}
