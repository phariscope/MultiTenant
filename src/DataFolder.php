<?php

namespace Phariscope\MultiTenant;

class DataFolder implements DataFolderPathInterface
{
    public function getDataRootFolder(): string
    {
        /** @var string $data_path */
        $data_path = $_ENV["DATA_PATH"];
        return $data_path;
    }

    public function getTenantDataFolder(): string
    {
        /** @var string $tid */
        $tid = $_ENV["TENANT_ID"];
        return sprintf("%s/%s", $this->getDataRootFolder(), $tid);
    }
}
