<?php

namespace Phariscope\MultiTenant\Tests;

use Phariscope\MultiTenant\DataFolder;
use PHPUnit\Framework\TestCase;

class DataFolderTest extends TestCase
{
    public function testGetDataRootFolder(): void
    {
        // Arrange
        $_ENV['DATA_PATH'] = '/var/data';
        $dataFolder = new DataFolder();

        // Act
        $result = $dataFolder->getDataRootFolder();

        // Assert
        $this->assertEquals('/var/data', $result);
    }

    public function testGetTenantDataFolder(): void
    {
        // Arrange
        $_ENV['DATA_PATH'] = '/var/data';
        $_ENV['TENANT_ID'] = 'tenant1';
        $dataFolder = new DataFolder();

        // Act
        $result = $dataFolder->getTenantDataFolder();

        // Assert
        $this->assertEquals('/var/data/tenants/tenant1', $result);
    }

    /**
     * Test TDD : La nouvelle implémentation devrait accepter un tenant ID en paramètre
     */
    public function testGetTenantDataFolderWithTenantId(): void
    {
        // Arrange
        $_ENV['DATA_PATH'] = '/var/data';
        $dataFolder = new DataFolder();
        $tenantId = 'tenantID1234';
        $expectedPath = '/var/data/tenants/tenantID1234';

        // Act
        $actualPath = $dataFolder->getTenantDataFolder($tenantId);

        // Assert
        $this->assertEquals($expectedPath, $actualPath);
    }

    /**
     * Test TDD : Vérifier que la structure de dossiers correspond au README
     */
    public function testGetTenantDatabaseFolder(): void
    {
        // Arrange
        $_ENV['DATA_PATH'] = '/var/data';
        $dataFolder = new DataFolder();
        $tenantId = 'tenantID1234';
        $expectedPath = '/var/data/tenants/tenantID1234/database';

        // Act
        $actualPath = $dataFolder->getTenantDatabaseFolder($tenantId);

        // Assert
        $this->assertEquals($expectedPath, $actualPath);
    }

    public function testGetTenantDatabasePath(): void
    {
        // Arrange
        $_ENV['DATA_PATH'] = '/var/data';
        $_ENV['DATABASE_URL'] = 'sqlite:///%DATA_PATH%/database/mydatabase.sqlite';
        $dataFolder = new DataFolder();
        $tenantId = 'tenantID1234';
        $expectedPath = '/var/data/tenants/tenantID1234/database/mydatabase.sqlite';

        // Act
        $actualPath = $dataFolder->getTenantDatabasePath($tenantId);

        // Assert
        $this->assertEquals($expectedPath, $actualPath);
    }

    public function testGetTenantDatabasePathWithComplexDatabaseUrl(): void
    {
        // Arrange
        $_ENV['DATA_PATH'] = '/var/data';
        $_ENV['DATABASE_URL'] = 'sqlite:///%DATA_PATH%/db/subfolder/databasename';
        $dataFolder = new DataFolder();
        $tenantId = 'tenantID1234';
        $expectedPath = '/var/data/tenants/tenantID1234/db/subfolder/databasename';

        // Act
        $actualPath = $dataFolder->getTenantDatabasePath($tenantId);

        // Assert
        $this->assertEquals($expectedPath, $actualPath);
    }

    /**
     * Test TDD : Vérifier que les chemins relatifs fonctionnent correctement
     */
    public function testGetTenantDataFolderWithRelativePath(): void
    {
        // Arrange
        $_ENV['DATA_PATH'] = './var/data';
        $dataFolder = new DataFolder();
        $tenantId = 'tenantID1234';
        $expectedPath = './var/data/tenants/tenantID1234';

        // Act
        $actualPath = $dataFolder->getTenantDataFolder($tenantId);

        // Assert
        $this->assertEquals($expectedPath, $actualPath);
    }

    public function testUnrecognizedDatabaseUrlException(): void
    {
        // Arrange
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("DATABASE_URL 'sqlite:///%DATA_PATH%@' is not a valid SQLite URL");

        $_ENV['DATABASE_URL'] = 'sqlite:///%DATA_PATH%@';
        $_ENV['DATA_PATH'] = '/var/data';
        $dataFolder = new DataFolder();

        // Act
        $dataFolder->getTenantDatabasePath('tenantID1234');

        // Assert - PHPUnit verifies the exception
    }
}
