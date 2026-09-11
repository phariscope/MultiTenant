<?php

declare(strict_types=1);

namespace Phariscope\MultiTenant\Command\Migrations;

use PDO;
use Phariscope\MultiTenant\DataFolder;

class TenantMigrationDatabaseInspector
{
    public function resolveTenantDatabasePath(string $tenantId): string
    {
        $originalDataPath = $_ENV['DATA_PATH'] ?? null;
        $dataFolder = new DataFolder();
        $applicationRoot = $dataFolder->getApplicationDataRoot();
        $_ENV['DATA_PATH'] = $applicationRoot;

        try {
            return (new DataFolder())->getTenantDatabasePath($tenantId);
        } finally {
            if ($originalDataPath !== null) {
                $_ENV['DATA_PATH'] = $originalDataPath;
            } else {
                unset($_ENV['DATA_PATH']);
            }
        }
    }

    public function tenantDatabaseExists(string $tenantId): bool
    {
        return is_file($this->resolveTenantDatabasePath($tenantId));
    }

    public function countExecutedMigrations(string $tenantId): int
    {
        $databasePath = $this->resolveTenantDatabasePath($tenantId);
        if (!is_file($databasePath)) {
            return 0;
        }

        $pdo = new PDO('sqlite:' . $databasePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $tableExists = $pdo->query(
            "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'doctrine_migration_versions'"
        );
        if ($tableExists === false || (int) $tableExists->fetchColumn() === 0) {
            return 0;
        }

        $count = $pdo->query('SELECT COUNT(*) FROM doctrine_migration_versions');

        return $count === false ? 0 : (int) $count->fetchColumn();
    }
}
