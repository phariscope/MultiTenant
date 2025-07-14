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
        $this->assertEquals('/var/data/tenants/tenant1', $dataFolder->getTenantDataFolder());
    }

    /**
     * Test TDD : La nouvelle implémentation devrait accepter un tenant ID en paramètre
     */
    public function testGetTenantDataFolderWithTenantId(): void
    {
        $_ENV["DATA_PATH"] = "/var/data";
        $dataFolder = new DataFolder();
        $tenantId = "tenantID1234";

        $expectedPath = "/var/data/tenants/tenantID1234";
        $actualPath = $dataFolder->getTenantDataFolder($tenantId);

        $this->assertEquals($expectedPath, $actualPath);
    }

    /**
     * Test TDD : Vérifier que la structure de dossiers correspond au README
     */
    public function testGetTenantDatabaseFolder(): void
    {
        $_ENV["DATA_PATH"] = "/var/data";
        $dataFolder = new DataFolder();
        $tenantId = "tenantID1234";

        $expectedPath = "/var/data/tenants/tenantID1234/database";
        $actualPath = $dataFolder->getTenantDatabaseFolder($tenantId);

        $this->assertEquals($expectedPath, $actualPath);
    }

    public function testGetTenantDatabasePath(): void
    {
        $_ENV["DATA_PATH"] = "/var/data";
        $_ENV["DATABASE_URL"] = "sqlite:///%DATA_PATH%/database/mydatabase.sqlite";

        $dataFolder = new DataFolder();
        $tenantId = "tenantID1234";

        $expectedPath = "/var/data/tenants/tenantID1234/database/mydatabase.sqlite";
        $actualPath = $dataFolder->getTenantDatabasePath($tenantId);

        $this->assertEquals($expectedPath, $actualPath);
    }

    public function testGetTenantDatabasePathWithComplexDatabaseUrl(): void
    {
        $_ENV["DATA_PATH"] = "/var/data";
        $_ENV["DATABASE_URL"] = "sqlite:///%DATA_PATH%/db/subfolder/databasename";

        $dataFolder = new DataFolder();
        $tenantId = "tenantID1234";

        $expectedPath = "/var/data/tenants/tenantID1234/db/subfolder/databasename";
        $actualPath = $dataFolder->getTenantDatabasePath($tenantId);

        $this->assertEquals($expectedPath, $actualPath);
    }

    /**
     * Test TDD : Vérifier que les chemins relatifs fonctionnent correctement
     */
    public function testGetTenantDataFolderWithRelativePath(): void
    {
        $_ENV["DATA_PATH"] = "./var/data";
        $dataFolder = new DataFolder();
        $tenantId = "tenantID1234";

        $expectedPath = "./var/data/tenants/tenantID1234";
        $actualPath = $dataFolder->getTenantDataFolder($tenantId);

        $this->assertEquals($expectedPath, $actualPath);
    }

    public function testUnrecognizedDatabaseUrlException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("DATABASE_URL 'sqlite:///%DATA_PATH%@' is not a valid SQLite URL");

        $_ENV["DATABASE_URL"] = "sqlite:///%DATA_PATH%@";
        $_ENV["DATA_PATH"] = "/var/data";

        $dataFolder = new DataFolder();
        $dataFolder->getTenantDatabasePath("tenantID1234");
    }
}
