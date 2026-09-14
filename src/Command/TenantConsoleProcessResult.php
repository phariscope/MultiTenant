<?php

declare(strict_types=1);

namespace Phariscope\MultiTenant\Command;

final class TenantConsoleProcessResult
{
    public function __construct(
        public readonly int $exitCode,
        public readonly string $output,
    ) {
    }
}
