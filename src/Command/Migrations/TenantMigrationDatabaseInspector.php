<?php

declare(strict_types=1);

namespace Phariscope\MultiTenant\Command\Migrations;

use PDO;
use Phariscope\MultiTenant\DataFolder;

class TenantMigrationDatabaseInspector
{
    public function resolveTenantDatabasePath(string $tenantId): string
    {
        $dataFolder = new DataFolder();
        $directory = $dataFolder->getAbsoluteTenantDataFolder($tenantId) . '/database';
        $fileName = $this->sqliteFileNameFromDatabaseUrl();
        $candidate = $directory . '/' . $fileName;
        if (is_file($candidate)) {
            return $candidate;
        }

        $matches = glob($directory . '/*.sqlite');
        if (is_array($matches) && $matches !== []) {
            return $matches[0];
        }

        return $candidate;
    }

    private function sqliteFileNameFromDatabaseUrl(): string
    {
        $databaseUrl = $_ENV['DATABASE_URL'] ?? '';
        if (!is_string($databaseUrl) || $databaseUrl === '') {
            return 'database.sqlite';
        }

        $fileName = basename(str_replace('\\', '/', $databaseUrl));
        if ($fileName === '' || !str_contains($fileName, '.')) {
            return 'database.sqlite';
        }

        return $fileName;
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
