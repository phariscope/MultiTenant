<?php

declare(strict_types=1);

namespace Phariscope\MultiTenant\Infrastructure\TenantShortname;

use InvalidArgumentException;
use PDO;
use PDOException;
use Phariscope\MultiTenant\DataFolder;
use RuntimeException;

/**
 * SQLite registry at {application DATA_PATH}/tenants/tenants.sqlite (global, not per-tenant).
 * Path calculation is delegated to DataFolder, which normalizes a tenant-scoped DATA_PATH.
 */
final class TenantShortnameRegistry
{
    private const MAX_UNIQUE_SHORTNAME_ATTEMPTS = 100;

    /** RFC 1035 maximum length of a single DNS label. */
    private const MAX_SHORTNAME_LENGTH = 63;

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

    public function resolveShortname(string $tenantId): ?string
    {
        $id = trim($tenantId);
        if ($id === '') {
            return null;
        }

        try {
            $stmt = $this->getPdo()->prepare(
                'SELECT tenant_shortname FROM tenant_shortname_map WHERE tenant_id = :t LIMIT 1'
            );
            $stmt->execute(['t' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row) || !isset($row['tenant_shortname']) || !is_string($row['tenant_shortname'])) {
                return null;
            }

            return $row['tenant_shortname'];
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

        if (!self::isAllowedShortname($tenantId, $key)) {
            throw new InvalidArgumentException(
                sprintf(
                    'tenant_shortname must be a DNS-like label (lowercase letters, digits, hyphen; 1-%d chars).',
                    self::MAX_SHORTNAME_LENGTH
                )
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

    /**
     * Registers a shortname for the tenant, allocating a unique variant when the desired slug is taken.
     * Tries the base slug first, then {@code base-2}, {@code base-3}, etc.
     *
     * @return non-empty-string the shortname actually stored (normalized)
     */
    public function registerUniqueShortname(string $tenantId, string $desiredShortname): string
    {
        if ($tenantId === '') {
            throw new InvalidArgumentException('tenant_id must not be empty.');
        }

        $base = self::normalizeShortname($desiredShortname);
        if ($base === '') {
            throw new InvalidArgumentException('tenant_shortname must not be empty.');
        }

        if (!self::isAllowedShortname($tenantId, $base)) {
            throw new InvalidArgumentException(
                sprintf(
                    'tenant_shortname must be a DNS-like label (lowercase letters, digits, hyphen; 1-%d chars).',
                    self::MAX_SHORTNAME_LENGTH
                )
            );
        }

        for ($attempt = 0; $attempt < self::MAX_UNIQUE_SHORTNAME_ATTEMPTS; ++$attempt) {
            $candidate = $attempt === 0
                ? $base
                : self::buildSuffixedShortname($base, $attempt + 1);

            if (!self::isAllowedShortname($tenantId, $candidate)) {
                continue;
            }

            $owner = $this->resolveTenantId($candidate);
            if ($owner !== null && $owner !== $tenantId) {
                continue;
            }

            try {
                $this->register($tenantId, $candidate);

                return $candidate;
            } catch (PDOException $e) {
                if (!self::isUniqueConstraintViolation($e)) {
                    throw $e;
                }
            }
        }

        throw new RuntimeException(
            sprintf(
                'Could not allocate a unique tenant_shortname after %d attempts (base: %s).',
                self::MAX_UNIQUE_SHORTNAME_ATTEMPTS,
                $base
            )
        );
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
        if (strlen($normalized) < 1 || strlen($normalized) > self::MAX_SHORTNAME_LENGTH) {
            return false;
        }

        if ($normalized[0] === '-' || substr($normalized, -1) === '-') {
            return false;
        }

        return preg_match('/^[a-z0-9-]+$/', $normalized) === 1;
    }

    private static function isAllowedShortname(string $tenantId, string $normalized): bool
    {
        if (strtolower($tenantId) === $normalized) {
            return true;
        }

        if (self::isValidShortname($normalized)) {
            return true;
        }

        $tenantLower = strtolower($tenantId);
        $pattern = '/^' . preg_quote($tenantLower, '/') . '-\d+$/';

        return preg_match($pattern, $normalized) === 1;
    }

    /**
     * @return non-empty-string
     */
    private static function buildSuffixedShortname(string $base, int $suffixNumber): string
    {
        $suffix = '-' . (string) $suffixNumber;
        $maxBaseLength = self::MAX_SHORTNAME_LENGTH - strlen($suffix);
        $truncated = substr($base, 0, max(1, $maxBaseLength));
        $truncated = rtrim($truncated, '-');
        if ($truncated === '') {
            throw new InvalidArgumentException('tenant_shortname base is too long to append a numeric suffix.');
        }

        return $truncated . $suffix;
    }

    private static function isUniqueConstraintViolation(PDOException $e): bool
    {
        if ($e->getCode() === '23000') {
            return true;
        }

        $message = $e->getMessage();

        return stripos($message, 'UNIQUE constraint failed') !== false;
    }
}
