<?php

namespace Phariscope\MultiTenant\Share;

use Phariscope\MultiTenant\DataFolder;
use Phariscope\MultiTenant\Infrastructure\TenantShortname\TenantShortnameRegistry;

class TenantExistenceChecker
{
    public function __construct(
        private readonly string $applicationDataPath,
        private readonly ?TenantShortnameRegistry $shortnameRegistry = null,
    ) {
    }

    /**
     * HTTP validation: tenant_id requires an existing tenant folder; tenant_shortname requires a registry row.
     *
     * @throws TenantException
     */
    public function assertResolvableForHttp(?string $tenantId, ?string $tenantShortname): ?string
    {
        $tenantId = $this->normalizeProvidedValue($tenantId);
        $tenantShortname = $this->normalizeProvidedValue($tenantShortname);

        if ($tenantId === null && $tenantShortname === null) {
            return null;
        }

        $registry = $this->getShortnameRegistry();

        if ($tenantId !== null && $tenantShortname !== null) {
            $this->assertTenantDirectoryExists($tenantId);
            $resolvedFromShortname = $registry->resolveTenantId($tenantShortname);
            if ($resolvedFromShortname === null) {
                throw TenantException::unknownShortname($tenantShortname);
            }
            if ($resolvedFromShortname !== $tenantId) {
                throw TenantException::tenantShortnameMismatch($tenantId, $tenantShortname);
            }

            return $tenantId;
        }

        if ($tenantId !== null) {
            $this->assertTenantDirectoryExists($tenantId);

            return $tenantId;
        }

        $resolved = $registry->resolveTenantId($tenantShortname);
        if ($resolved === null) {
            throw TenantException::unknownShortname($tenantShortname);
        }

        return $resolved;
    }

    /**
     * @throws TenantException
     */
    private function assertTenantDirectoryExists(string $tenantId): void
    {
        if (!is_dir($this->getTenantDirectoryPath($tenantId))) {
            throw TenantException::unknownTenantId($tenantId);
        }
    }

    private function getShortnameRegistry(): TenantShortnameRegistry
    {
        return $this->shortnameRegistry
            ?? TenantShortnameRegistry::fromApplicationDataPath($this->applicationDataPath);
    }

    private function getTenantDirectoryPath(string $tenantId): string
    {
        $originalDataPath = $_ENV['DATA_PATH'] ?? null;
        $_ENV['DATA_PATH'] = $this->applicationDataPath;

        try {
            return (new DataFolder())->getTenantDataFolder($tenantId);
        } finally {
            if ($originalDataPath !== null) {
                $_ENV['DATA_PATH'] = $originalDataPath;
            } else {
                unset($_ENV['DATA_PATH']);
            }
        }
    }

    private function normalizeProvidedValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
