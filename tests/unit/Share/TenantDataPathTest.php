<?php

namespace Phariscope\MultiTenant\Tests\Share;

use Phariscope\MultiTenant\Share\TenantDataPath;
use Phariscope\MultiTenant\Share\DataPathException;
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
        // Arrange
        $savedEnvDataPath = $_ENV['DATA_PATH'] ?? null;
        unset($_ENV['DATA_PATH']);
        $this->expectException(DataPathException::class);
        $this->expectExceptionMessage('DATA_PATH environment variable is not set');
        $sut = new TenantDataPath();

        try {
            // Act
            $sut->getTenantDataPath('tenant123');
        } finally {
            if ($savedEnvDataPath !== null) {
                $_ENV['DATA_PATH'] = $savedEnvDataPath;
            } else {
                unset($_ENV['DATA_PATH']);
            }
        }

        // Assert - PHPUnit verifies the exception
    }
}
