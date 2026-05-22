<?php

declare(strict_types=1);

namespace Phariscope\MultiTenant\Infrastructure\TenantShortname;

use InvalidArgumentException;
use PDO;
use PDOException;
use Phariscope\MultiTenant\DataFolder;

/**
 * SQLite registry at {application DATA_PATH}/tenants/tenants.sqlite (global, not per-tenant).
 * Path calculation is delegated to DataFolder, which normalizes a tenant-scoped DATA_PATH.
 */
final class TenantShortnameRegistry
{
    private ?PDO $pdo = null;

    public function __construct(
        private readonly string $sqliteFilePath,
    ) {
    }

    public static function tryCreateFromEnv(): ?self
    {
        $dataPath = $_ENV['DATA_PATH'] ?? getenv('DATA_PATH');
        if (!is_string($dataPath) || $dataPath === '') {
            return null;
        }

        return self::fromApplicationDataPath($dataPath);
    }

    public static function fromApplicationDataPath(string $dataPath): self
    {
        // Temporairement, on set DATA_PATH pour que DataFolder fonctionne
        $originalDataPath = $_ENV['DATA_PATH'] ?? null;
        $_ENV['DATA_PATH'] = $dataPath;

        try {
            $dataFolder = new DataFolder();
            $sqliteFilePath = $dataFolder->getDatabaseTenantsFullPath();

            return new self($sqliteFilePath);
        } finally {
            // Restaurer la valeur originale
            if ($originalDataPath !== null) {
                $_ENV['DATA_PATH'] = $originalDataPath;
            } else {
                unset($_ENV['DATA_PATH']);
            }
        }
    }

    public function getSqliteFilePath(): string
    {
        return $this->sqliteFilePath;
    }

    public function resolveTenantId(string $tenantShortname): ?string
    {
        $key = self::normalizeShortname($tenantShortname);
        if ($key === '') {
            return null;
        }

        try {
            $stmt = $this->getPdo()->prepare(
                'SELECT tenant_id FROM tenant_shortname_map WHERE tenant_shortname = :s LIMIT 1'
            );
            $stmt->execute(['s' => $key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row) || !isset($row['tenant_id']) || !is_string($row['tenant_id'])) {
                return null;
            }

            return $row['tenant_id'];
        } catch (PDOException) {
            return null;
        }
    }

    public function register(string $tenantId, string $tenantShortname): void
    {
        if ($tenantId === '') {
            throw new InvalidArgumentException('tenant_id must not be empty.');
        }

        $key = self::normalizeShortname($tenantShortname);
        if ($key === '') {
            throw new InvalidArgumentException('tenant_shortname must not be empty.');
        }

        if (strtolower($tenantId) !== $key && !self::isValidShortname($key)) {
            throw new InvalidArgumentException(
                'tenant_shortname must be a DNS-like label (lowercase letters, digits, hyphen; 1-63 chars).'
            );
        }

        $pdo = $this->getPdo();
        $pdo->beginTransaction();
        try {
            $del = $pdo->prepare('DELETE FROM tenant_shortname_map WHERE tenant_id = :t');
            $del->execute(['t' => $tenantId]);
            $stmt = $pdo->prepare(
                'INSERT INTO tenant_shortname_map (tenant_shortname, tenant_id) VALUES (:s, :t)'
            );
            $stmt->execute(['s' => $key, 't' => $tenantId]);
            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private function getPdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $dir = dirname($this->sqliteFilePath);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new PDOException('Cannot create directory for tenant shortname registry: ' . $dir);
            }
        }

        $this->pdo = new PDO('sqlite:' . $this->sqliteFilePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS tenant_shortname_map (
                tenant_shortname TEXT NOT NULL UNIQUE,
                tenant_id TEXT NOT NULL PRIMARY KEY
            )'
        );

        return $this->pdo;
    }

    private static function normalizeShortname(string $tenantShortname): string
    {
        return strtolower(trim($tenantShortname));
    }

    private static function isValidShortname(string $normalized): bool
    {
        if (strlen($normalized) < 1 || strlen($normalized) > 63) {
            return false;
        }

        if ($normalized[0] === '-' || substr($normalized, -1) === '-') {
            return false;
        }

        return preg_match('/^[a-z0-9-]+$/', $normalized) === 1;
    }
}
