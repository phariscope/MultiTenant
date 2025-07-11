<?php

namespace Phariscope\MultiTenant;

class DataFolder implements DataFolderPathInterface
{
    private const TENANTS_SUB_FOLDER = "tenants";

    public function getDataRootFolder(): string
    {
        return $_ENV["DATA_PATH"];
    }

    public function getTenantDataFolder(): string
    {
        return sprintf("%s/%s/%s", $this->getDataRootFolder(), self::TENANTS_SUB_FOLDER, $_ENV["TENANT_ID"]);
    }
}
