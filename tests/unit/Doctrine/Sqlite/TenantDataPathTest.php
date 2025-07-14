<?php

namespace Phariscope\MultiTenant\Tests\Doctrine\Sqlite;

use Phariscope\MultiTenant\Doctrine\Sqlite\TenantDataPath;
use Phariscope\MultiTenant\Doctrine\Sqlite\DataPathException;
use PHPUnit\Framework\TestCase;

class TenantDataPathTest extends TestCase
{
    public function testConstructorWithDataPath(): void
    {
        // Arrange
        $tenantId = 'tenant123';
        $dataPath = '../data/myApp';

        // Act
        $sut = new TenantDataPath($dataPath, $tenantId);
        $result = $sut->getTenantDataPath();

        // Assert
        $this->assertEquals('../data/myApp/tenants/tenant123', $result);
        $this->assertEquals($dataPath, $sut->getDataPath());
    }

    public function testConstructorWithDataPathFromEnv(): void
    {
        // Arrange
        $tenantId = 'tenant123';
        $dataPath = '../data/myApp';

        if (isset($_ENV['DATA_PATH'])) {
            $savedEnvDataPath = $_ENV['DATA_PATH'];
        }

        $_ENV['DATA_PATH'] = $dataPath;

        // Act
        $sut = new TenantDataPath();
        $result = $sut->getTenantDataPath($tenantId);

        // Assert
        $this->assertEquals($dataPath, $result);

        // clean up
        if (isset($savedEnvDataPath)) {
            $_ENV['DATA_PATH'] = $savedEnvDataPath;
        } else {
            unset($_ENV['DATA_PATH']);
        }
    }

    public function testNoDataPathEnvException(): void
    {
        $this->expectException(DataPathException::class);
        $this->expectExceptionMessage('DATA_PATH environment variable is not set');

        // Arrange
        $tenantId = 'tenant123';

        if (isset($_ENV['DATA_PATH'])) {
            $savedEnvDataPath = $_ENV['DATA_PATH'];
        }
        unset($_ENV['DATA_PATH']);

        // Act
        $sut = new TenantDataPath();
        $result = $sut->getTenantDataPath($tenantId);

        // clean up
        if (isset($savedEnvDataPath)) {
            $_ENV['DATA_PATH'] = $savedEnvDataPath;
        } else {
            unset($_ENV['DATA_PATH']);
        }
    }
}
