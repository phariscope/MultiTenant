<?php

namespace Phariscope\MultiTenant\Tests\Doctrine\Sqlite;

use Phariscope\MultiTenant\Doctrine\Sqlite\PathTransformer;
use Phariscope\MultiTenant\Doctrine\Sqlite\DataPathException;
use PHPUnit\Framework\TestCase;

class PathTransformerTest extends TestCase
{
    private ?string $savedEnvDataPath = null;

    protected function setUp(): void
    {
        parent::setUp();
        if (isset($_ENV['DATA_PATH'])) {
            $this->savedEnvDataPath = $_ENV['DATA_PATH'];
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if ($this->savedEnvDataPath !== null) {
            $_ENV['DATA_PATH'] = $this->savedEnvDataPath;
        } else {
            unset($_ENV['DATA_PATH']);
        }
    }

    public function testTransformSimple(): void
    {
        // Arrange
        $tenantId = 'tenant123';
        $additionalPath = '/tenants/' . $tenantId; // '/tenants/tenant123'
        $dataPath = '/var/data';

        $initialPath = $dataPath . '/database.sqlite'; // 'vfs://root/data/database.sqlite'

        // 'vfs://root/data/tenants/tenant123/database.sqlite'
        $expectedPath = $dataPath . $additionalPath . '/database.sqlite';

        // Act
        $sut = new PathTransformer($dataPath);
        $result = $sut->transform($initialPath, $tenantId);

        // Assert
        $this->assertEquals($expectedPath, $result);
    }

    public function testTransformWithSubfolder(): void
    {
        // Arrange
        $tenantId = 'tenant123';
        $additionalPath = '/tenants/' . $tenantId;
        $dataPath = '/var/data';

        $initialPath = $dataPath . '/subfolder/database.sqlite';
        $expectedPath = $dataPath . $additionalPath . '/subfolder/database.sqlite';

        // Act
        $sut = new PathTransformer($dataPath);
        $result = $sut->transform($initialPath, $tenantId);

        // Assert
        $this->assertEquals($expectedPath, $result);
    }

    public function testTransformWithTwoSubfolders(): void
    {
        // Arrange
        $tenantId = 'tenant123';
        $additionalPath = '/tenants/' . $tenantId;
        $dataPath = '/var/data';

        $initialPath = $dataPath . '/subfolder/subsubfolder/database.sqlite';
        $expectedPath = $dataPath . $additionalPath . '/subfolder/subsubfolder/database.sqlite';

        // Act
        $sut = new PathTransformer($dataPath);
        $result = $sut->transform($initialPath, $tenantId);

        // Assert
        $this->assertEquals($expectedPath, $result);
    }

    public function testTransformWithRelativeDataPath(): void
    {
        // Arrange
        $tenantId = 'tenant123';
        $dataPath = '../data/myApp';

        $initialPath = '/var/myApp/data/myApp/database/myApp.sqlite';
        $expectedPath = '/var/myApp/data/myApp/tenants/tenant123/database/myApp.sqlite';

        // Act
        $sut = new PathTransformer($dataPath);
        $result = $sut->transform($initialPath, $tenantId);

        // Assert
        $this->assertEquals($expectedPath, $result);
    }

    public function testTransformWithDoubleParentFolderRelativeDataPath(): void
    {
        // Arrange
        $tenantId = 'tenant123';
        $dataPath = '../../data/myApp';

        $initialPath = '/var/myApp/data/myApp/database/myApp.sqlite';
        $expectedPath = '/var/myApp/data/myApp/tenants/tenant123/database/myApp.sqlite';

        // Act
        $sut = new PathTransformer($dataPath);
        $result = $sut->transform($initialPath, $tenantId);

        // Assert
        $this->assertEquals($expectedPath, $result);
    }

    public function testTransformWithRelativeInsideFolderDataPath(): void
    {
        // Arrange
        $tenantId = 'tenant123';
        $dataPath = './data/myApp';

        $initialPath = '/var/myApp/data/myApp/database/myApp.sqlite';
        $expectedPath = '/var/myApp/data/myApp/tenants/tenant123/database/myApp.sqlite';

        // Act
        $sut = new PathTransformer($dataPath);
        $result = $sut->transform($initialPath, $tenantId);

        // Assert
        $this->assertEquals($expectedPath, $result);
    }

    public function testUndefinedDataPathException(): void
    {
        $this->expectException(DataPathException::class);
        $this->expectExceptionMessage('DATA_PATH environment variable is not set');

        // Arrange
        unset($_ENV['DATA_PATH']);

        $tenantId = 'tenant123';

        $initialPath = '/var/myApp/data/myApp/database/myApp.sqlite';
        $expectedPath = '/var/myApp/data/myApp/database/tenants/tenant123/myApp.sqlite';

        // Act
        $sut = new PathTransformer();
        $result = $sut->transform($initialPath, $tenantId);

        // Assert
        $this->assertEquals($expectedPath, $result);
    }

    public function testTransformerWithDataPathFromEnv(): void
    {
        // Arrange
        $tenantId = 'tenant123';
        $_ENV['DATA_PATH'] = '../data/myApp';

        $initialPath = '/var/myApp/data/myApp/database/myApp.sqlite';
        $expectedPath = '/var/myApp/data/myApp/tenants/tenant123/database/myApp.sqlite';

        // Act
        $sut = new PathTransformer();
        $result = $sut->transform($initialPath, $tenantId);

        // Assert
        $this->assertEquals($expectedPath, $result);
    }

    public function testDataPathPatternNotFound(): void
    {
        $initialPathWithBad = '/var/myApp/data/bad-pattern/database/myApp.sqlite';
        $this->expectException(DataPathException::class);
        $this->expectExceptionMessage("DATA_PATH pattern 'data/myApp' not found in path '$initialPathWithBad'");

        // Arrange
        $_ENV['DATA_PATH'] = '../data/myApp';



        // Act
        $sut = new PathTransformer();
        $result = $sut->transform($initialPathWithBad, 'tenant123');
    }
}
