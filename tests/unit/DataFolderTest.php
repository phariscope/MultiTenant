<?php

namespace Phariscope\MultiTenant\Tests;

use Phariscope\MultiTenant\DataFolder;
use PHPUnit\Framework\TestCase;

class DataFolderTest extends TestCase
{
    public function testGetDataRootFolder(): void
    {
        $_ENV["DATA_PATH"] = "/var/data";
        $dataFolder = new DataFolder();
        $this->assertEquals('/var/data', $dataFolder->getDataRootFolder());
    }

    public function testGetTenantDataFolder(): void
    {
        $_ENV["DATA_PATH"] = "/var/data";
        $_ENV["TENANT_ID"] = "tenant1";
        $dataFolder = new DataFolder();
        $this->assertEquals('/var/data/tenant1', $dataFolder->getTenantDataFolder());
    }
}
