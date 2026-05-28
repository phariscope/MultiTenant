<?php

namespace Phariscope\MultiTenant;

use Phariscope\MultiTenant\Doctrine\Sqlite\PathTransformer;
use Phariscope\MultiTenant\Doctrine\Tools\TenantManager;
use Phariscope\MultiTenant\Infrastructure\TenantShortname\TenantShortnameRegistry;
use Phariscope\MultiTenant\Share\TenantDataPath;
use Phariscope\MultiTenant\Share\TenantException;

class ContextTransformer
{
    /** @var array<string, mixed> */
    private array $context;
    private ?string $initialDataPath;
    private ?string $initialDatabaseUrl;
    private bool $tenantIdResolved = false;
    private ?string $resolvedTenantId = null;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(array &$context)
    {
        $this->context = &$context;
        $this->initialDataPath = is_string($context['DATA_PATH'] ?? null) ? $context['DATA_PATH'] : null;
        $this->initialDatabaseUrl = is_string($context['DATABASE_URL'] ?? null) ? $context['DATABASE_URL'] : null;
    }

    public function transformDataPath(): void
    {
        $tenantId = $this->extractTenantIdFromContext();

        if ($tenantId !== null && $this->initialDataPath !== null) {
            $tenantDatapath = new TenantDataPath($this->initialDataPath, $tenantId);
            $this->context['DATA_PATH'] = $tenantDatapath->getTenantDataPath();
            $_ENV['DATA_PATH'] = $this->context['DATA_PATH'];
            putenv('DATA_PATH=' . $this->context['DATA_PATH']);
        }
    }

    /**
     * @throws TenantException
     */
    private function extractTenantIdFromContext(): ?string
    {
        if ($this->tenantIdResolved) {
            return $this->resolvedTenantId;
        }

        $tenantId = $this->extractTenantIdFromArgv();
        $shortname = $this->extractTenantShortnameFromArgv();

        if ($tenantId !== null || $shortname !== null) {
            if ($this->initialDataPath === null) {
                if ($shortname !== null) {
                    throw TenantException::cannotResolveShortname($shortname);
                }

                throw TenantException::unknownTenantId($tenantId);
            }

            $this->resolvedTenantId = $this->resolveConsoleTenantId($tenantId, $shortname);
            $this->tenantIdResolved = true;

            return $this->resolvedTenantId;
        }

        $tenantManager = new TenantManager();
        $this->resolvedTenantId = $tenantManager->getCurrentTenantId();
        $this->tenantIdResolved = true;

        return $this->resolvedTenantId;
    }

    private function resolveConsoleTenantId(?string $tenantId, ?string $shortname): ?string
    {
        if ($tenantId !== null) {
            return $tenantId;
        }

        if ($shortname === null || $this->initialDataPath === null) {
            return null;
        }

        return TenantShortnameRegistry::fromApplicationDataPath($this->initialDataPath)
            ->resolveTenantId($shortname);
    }

    private function extractTenantIdFromArgv(): ?string
    {
        if (!isset($this->context['argv']) || !is_array($this->context['argv'])) {
            return null;
        }

        $argv = $this->context['argv'];

        // Recherche l'option --tenant_id suivie de sa valeur dans l'argument suivant
        for ($i = 0; $i < count($argv) - 1; $i++) {
            if ($argv[$i] === '--tenant_id') {
                $value = $argv[$i + 1];
                return is_string($value) && trim($value) !== '' ? $value : null;
            }
        }

        // Maintien de la compatibilité avec l'ancien format --tenant_id=value
        foreach ($argv as $arg) {
            if (strpos($arg, '--tenant_id=') === 0) {
                $value = substr($arg, strlen('--tenant_id='));
                return trim($value) !== '' ? $value : null;
            }
        }

        return null;
    }

    private function extractTenantShortnameFromArgv(): ?string
    {
        if (!isset($this->context['argv']) || !is_array($this->context['argv'])) {
            return null;
        }

        $argv = $this->context['argv'];

        for ($i = 0; $i < count($argv) - 1; $i++) {
            if ($argv[$i] === '--tenant_shortname') {
                $value = $argv[$i + 1];
                return is_string($value) && trim($value) !== '' ? $value : null;
            }
        }

        foreach ($argv as $arg) {
            if (strpos($arg, '--tenant_shortname=') === 0) {
                $value = substr($arg, strlen('--tenant_shortname='));
                return trim($value) !== '' ? $value : null;
            }
        }

        return null;
    }

    public function transformDatabaseUrl(): void
    {
        $tenantId = $this->extractTenantIdFromContext();

        if ($tenantId !== null && $this->initialDatabaseUrl !== null) {
            $databaseUrl = $this->context['DATABASE_URL'];

            // Pour SQLite, on insert le tenant dans le chemin
            if (is_string($databaseUrl) && strpos($databaseUrl, 'sqlite://') === 0 && $this->initialDataPath !== null) {
                $sqlitePathTransformer = new PathTransformer($this->initialDataPath);
                $this->context['DATABASE_URL'] = $sqlitePathTransformer->transform($databaseUrl, $tenantId);
                $_ENV['DATABASE_URL'] = $this->context['DATABASE_URL'];
                putenv('DATABASE_URL=' . $this->context['DATABASE_URL']);
            }
        }
    }
}
