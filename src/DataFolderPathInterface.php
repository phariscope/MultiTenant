<?php

namespace Phariscope\MultiTenant;

interface DataFolderPathInterface
{
    public function getDataRootFolder(): string;

    public function getTenantDataFolder(): string;
}
