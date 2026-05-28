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
     * Resolves and validates tenant identity when explicitly provided.
     *
     * @throws TenantException
     */
    public function assertResolvable(?string $tenantId, ?string $tenantShortname): ?string
    {
        $tenantId = $this->normalizeProvidedValue($tenantId);
        $tenantShortname = $this->normalizeProvidedValue($tenantShortname);

        if ($tenantId !== null) {
            $this->assertTenantDirectoryExists($tenantId);

            return $tenantId;
        }

        if ($tenantShortname === null) {
            return null;
        }

        $registry = $this->shortnameRegistry
            ?? TenantShortnameRegistry::fromApplicationDataPath($this->applicationDataPath);
        $resolved = $registry->resolveTenantId($tenantShortname);
        if ($resolved === null) {
            throw TenantException::unknownShortname($tenantShortname);
        }

        $this->assertTenantDirectoryExists($resolved);

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
