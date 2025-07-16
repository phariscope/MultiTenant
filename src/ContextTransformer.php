<?php

namespace Phariscope\MultiTenant;

use Phariscope\MultiTenant\Doctrine\Sqlite\PathTransformer;
use Phariscope\MultiTenant\Share\TenantDataPath;

class ContextTransformer
{
    /** @var array<string, mixed> */
    private array $context;
    private ?string $initialDataPath;
    private ?string $initialDatabaseUrl;

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
        $tenantId = $this->extractTenantIdFromArgv();

        if ($tenantId !== null && $this->initialDataPath !== null) {
            $tenantDatapath = new TenantDataPath($this->initialDataPath, $tenantId);
            $this->context['DATA_PATH'] = $tenantDatapath->getTenantDataPath();
            $_ENV['DATA_PATH'] = $this->context['DATA_PATH'];
        }
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
                return $argv[$i + 1];
            }
        }

        // Maintien de la compatibilité avec l'ancien format --tenant_id=value
        foreach ($argv as $arg) {
            if (strpos($arg, '--tenant_id=') === 0) {
                return substr($arg, strlen('--tenant_id='));
            }
        }

        return null;
    }

    public function transformDatabaseUrl(): void
    {
        $tenantId = $this->extractTenantIdFromArgv();

        if ($tenantId !== null && $this->initialDatabaseUrl !== null) {
            $databaseUrl = $this->context['DATABASE_URL'];

            // Pour SQLite, on insert le tenant dans le chemin
            if (is_string($databaseUrl) && strpos($databaseUrl, 'sqlite://') === 0 && $this->initialDataPath !== null) {
                $sqlitePathTransformer = new PathTransformer($this->initialDataPath);
                $this->context['DATABASE_URL'] = $sqlitePathTransformer->transform($databaseUrl, $tenantId);
                $_ENV['DATABASE_URL'] = $this->context['DATABASE_URL'];
            }
        }
    }
}
