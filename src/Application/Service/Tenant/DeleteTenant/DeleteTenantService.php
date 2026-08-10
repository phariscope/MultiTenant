<?php

declare(strict_types=1);

namespace Phariscope\MultiTenant\Application\Service\Tenant\DeleteTenant;

use InvalidArgumentException;
use Phariscope\MultiTenant\DataFolder;
use Phariscope\MultiTenant\Infrastructure\TenantShortname\TenantShortnameRegistry;
use RuntimeException;

/**
 * Removes a tenant data directory and its shortname registry mapping.
 */
final class DeleteTenantService
{
    public function __construct(
        private readonly ?TenantShortnameRegistry $shortnameRegistry = null,
        private readonly ?string $applicationDataPath = null,
    ) {
    }

    public function execute(string $tenantId): void
    {
        $tenantId = trim($tenantId);
        if ($tenantId === '') {
            throw new InvalidArgumentException('tenant_id must not be empty.');
        }
        if (str_contains($tenantId, '/') || str_contains($tenantId, '\\') || str_contains($tenantId, '..')) {
            throw new InvalidArgumentException(sprintf('Invalid tenant_id "%s".', $tenantId));
        }

        $appRoot = $this->resolveApplicationDataPath();
        $tenantDir = rtrim($appRoot, '/') . '/tenants/' . $tenantId;
        if (is_dir($tenantDir)) {
            $this->removeDir($tenantDir);
        }

        $registry = $this->shortnameRegistry
            ?? TenantShortnameRegistry::fromApplicationDataPath($appRoot);
        $registry->unregister($tenantId);
    }

    private function resolveApplicationDataPath(): string
    {
        if ($this->applicationDataPath !== null && $this->applicationDataPath !== '') {
            return $this->applicationDataPath;
        }

        $dataPath = $_ENV['DATA_PATH'] ?? getenv('DATA_PATH');
        if (!is_string($dataPath) || $dataPath === '') {
            throw new RuntimeException('DATA_PATH is not set; cannot delete tenant.');
        }

        $original = $_ENV['DATA_PATH'] ?? null;
        $_ENV['DATA_PATH'] = $dataPath;
        try {
            return (new DataFolder())->getApplicationDataRoot();
        } finally {
            if ($original !== null) {
                $_ENV['DATA_PATH'] = $original;
            } else {
                unset($_ENV['DATA_PATH']);
            }
        }
    }

    private function removeDir(string $dir): void
    {
        $items = scandir($dir);
        if ($items === false) {
            throw new RuntimeException(sprintf('Could not read tenant directory "%s".', $dir));
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDir($path);
            } elseif (!unlink($path)) {
                throw new RuntimeException(sprintf('Could not delete file "%s".', $path));
            }
        }

        if (!rmdir($dir)) {
            throw new RuntimeException(sprintf('Could not remove tenant directory "%s".', $dir));
        }
    }
}
