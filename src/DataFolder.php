<?php

namespace Phariscope\MultiTenant;

class DataFolder implements DataFolderPathInterface
{
    private const TENANTS_SUB_FOLDER = "tenants";
    private const DATABASE_SUB_FOLDER = "database";
    private const DATABASE_URL_NAME = "DATABASE_URL";
    private const DATA_PATH_NAME = "DATA_PATH";

    public function getDataRootFolder(): string
    {
        return $_ENV[self::DATA_PATH_NAME];
    }

    public function getTenantDataFolder(?string $tenantId = null): string
    {
        $tenant = $tenantId ?? $_ENV["TENANT_ID"];
        return sprintf("%s/%s/%s", $this->getDataRootFolder(), self::TENANTS_SUB_FOLDER, $tenant);
    }

    public function getTenantDatabaseFolder(string $tenantId): string
    {
        return sprintf("%s/%s", $this->getTenantDataFolder($tenantId), self::DATABASE_SUB_FOLDER);
    }

    public function getTenantDatabasePath(string $tenantId): string
    {
        // Extraire le chemin relatif complet depuis DATABASE_URL
        $databaseUrl = $_ENV[self::DATABASE_URL_NAME];

        // Gérer le format sqlite:///%DATA_PATH%/[chemin_relatif]
        if (preg_match('/sqlite:\/\/\/.*%' . self::DATA_PATH_NAME . '%\/(.+)$/', $databaseUrl, $matches)) {
            $relativePath = $matches[1];
            return sprintf("%s/%s", $this->getTenantDataFolder($tenantId), $relativePath);
        }

        throw new \InvalidArgumentException("DATABASE_URL '$databaseUrl' is not a valid SQLite URL");
    }

    public function getDatabaseTenantsFullPath(): string
    {
        return sprintf(
            "%s/%s/%s",
            $this->getApplicationDataRoot(),
            self::TENANTS_SUB_FOLDER,
            'tenants.sqlite'
        );
    }

    /**
     * Absolute tenant data directory under the application data root
     * ({root}/tenants/{tenantId}), even when DATA_PATH is already tenant-scoped.
     */
    public function getAbsoluteTenantDataFolder(string $tenantId): string
    {
        return sprintf(
            '%s/%s/%s',
            rtrim($this->getApplicationDataRoot(), '/'),
            self::TENANTS_SUB_FOLDER,
            $tenantId
        );
    }

    /**
     * Application data root (unscoped DATA_PATH), even when $_ENV['DATA_PATH'] was
     * narrowed to {root}/tenants/{tenantId} by ContextTransformer.
     */
    public function getApplicationDataRoot(): string
    {
        $dataRoot = $this->getDataRootFolder();
        $normalized = rtrim(str_replace('\\', '/', $dataRoot), '/');

        $pattern = sprintf('#/%s/([^/]+)$#', preg_quote(self::TENANTS_SUB_FOLDER, '#'));
        if (preg_match($pattern, $normalized, $matches) === 1) {
            return substr(
                $normalized,
                0,
                -strlen('/' . self::TENANTS_SUB_FOLDER . '/' . $matches[1])
            );
        }

        return $dataRoot;
    }
}
