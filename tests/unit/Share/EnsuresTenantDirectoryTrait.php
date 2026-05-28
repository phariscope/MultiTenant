<?php

declare(strict_types=1);

namespace Phariscope\MultiTenant\Tests\Share;

trait EnsuresTenantDirectoryTrait
{
    protected function ensureTenantDirectory(string $applicationDataPath, string $tenantId): void
    {
        $tenantDir = rtrim($applicationDataPath, '/') . '/tenants/' . $tenantId;
        if (!is_dir($tenantDir)) {
            mkdir($tenantDir, 0775, true);
        }
    }
}
