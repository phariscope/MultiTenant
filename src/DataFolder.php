<?php

namespace Phariscope\MultiTenant;

class DataFolder implements DataFolderPathInterface
{
    public function getDataRootFolder(): string
    {
        return $_ENV["DATA_PATH"];
    }

    public function getTenantDataFolder(): string
    {
        return sprintf("%s/%s", $this->getDataRootFolder(), $_ENV["TENANT_ID"]);
    }
}
