<?php

namespace Phariscope\MultiTenant\Share;

class TenantDataPath
{
    private string $dataPath;
    private ?string $tenantId;

    public function __construct(?string $dataPath = null, ?string $tenantId = null)
    {
        if ($dataPath === null && !isset($_ENV['DATA_PATH'])) {
            throw new DataPathException('DATA_PATH environment variable is not set');
        }
        $envDataPath = $_ENV['DATA_PATH'] ?? '';
        $this->dataPath = $dataPath ?? (is_string($envDataPath) ? $envDataPath : '');
        $this->tenantId = $tenantId;
    }

    public function getTenantDataPath(?string $tenantId = null): string
    {
        if ($tenantId !== null) {
            return $this->dataPath;
        }

        return $this->dataPath . '/tenants/' . $this->tenantId;
    }

    public function getDataPath(): string
    {
        return $this->dataPath;
    }
}
