<?php

declare(strict_types=1);

namespace Phariscope\MultiTenant\Tests\Command;

use PDO;

trait PreparesTenantSqliteFixture
{
    private function createTenantSqliteDatabase(string $tenantId): string
    {
        $path = $this->tenantSqlitePath($tenantId);
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        touch($path);

        return $path;
    }

    private function createTenantSqliteDatabaseWithMigrationHistory(string $tenantId, int $versionCount): string
    {
        $path = $this->createTenantSqliteDatabase($tenantId);
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->exec(
            'CREATE TABLE doctrine_migration_versions (version VARCHAR(191) NOT NULL PRIMARY KEY)'
        );

        for ($i = 1; $i <= $versionCount; ++$i) {
            $statement = $pdo->prepare('INSERT INTO doctrine_migration_versions (version) VALUES (:version)');
            $statement->execute(['version' => 'Version' . $i]);
        }

        return $path;
    }

    private function tenantSqlitePath(string $tenantId): string
    {
        return $this->getIsolatedDataPathDir()
            . '/tenants/'
            . $tenantId
            . '/database/database.sqlite';
    }
}
