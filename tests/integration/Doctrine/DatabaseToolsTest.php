<?php

namespace Phariscope\MultiTenant\Tests\Integration\Doctrine;

use Doctrine\DBAL\DriverManager;
use Phariscope\MultiTenant\Doctrine\DatabaseTools;
use Phariscope\MultiTenant\Tests\Doctrine\Tools\FakeEntityManagerFactory;
use PHPUnit\Framework\TestCase;

/**
 * @psalm-import-type Params from DriverManager
 */
class DatabaseToolsTest extends TestCase
{
    protected function setUp(): void
    {
        (new FakeEntityManagerFactory())->cleanMariadbDatabase();
    }

    public function testCreateMysqlDatabase(): void
    {
        // Arrange
        $em = (new FakeEntityManagerFactory())->createMariadbEntityManager();
        $sut = new DatabaseTools();

        // Act
        $sut->createDatabase($em);
        $params = $em->getConnection()->getParams();

        // Assert
        $this->assertEquals(FakeEntityManagerFactory::MARIADB_DATABASE_NAME, $this->getParam($params, 'dbname'));
        $this->assertEquals('pdo_mysql', $this->getParam($params, 'driver'));
        $this->assertEquals('root', $this->getParam($params, 'user'));
        $this->assertEquals('password', $this->getParam($params, 'password'));
        $this->assertEquals('10.11.5-MariaDB', $this->getParam($params, 'serverVersion'));
        $this->assertEquals('utf8mb4', $this->getParam($params, 'charset'));
    }

    /**
     * @psalm-param Params $params
     */
    private function getParam(array $params, string $key): mixed
    {
        if (isset($params[$key])) {
            return $params[$key];
        }

        throw new \InvalidArgumentException(ucfirst($key) . ' not found');
    }

    public function testDatabaseExists(): void
    {
        // Arrange
        $em = (new FakeEntityManagerFactory())->createMariadbEntityManager();
        $sut = new DatabaseTools();
        $sut->createDatabase($em);

        // Act
        $exists = $sut->databaseExists($em);

        // Assert
        $this->assertTrue($exists);
    }

    public function testDatabaseDoesNotExist(): void
    {
        // Arrange
        $em = (new FakeEntityManagerFactory())->createMariadbEntityManager();
        $sut = new DatabaseTools();

        // Act
        $exists = $sut->databaseExists($em);

        // Assert
        $this->assertFalse($exists);
    }

    public function testDatabaseDrop(): void
    {
        // Arrange
        $em = (new FakeEntityManagerFactory())->createMariadbEntityManager();
        $sut = new DatabaseTools();
        $sut->createDatabase($em);

        // Act
        $sut->dropDatabase($em);

        // Assert
        $this->assertFalse($sut->databaseExists($em));
    }

    public function testCreateDatabaseIfNotExists(): void
    {
        // Arrange
        $em = (new FakeEntityManagerFactory())->createMariadbEntityManager();
        $sut = new DatabaseTools();

        // Act
        $sut->createDatabaseIfNotExists($em);
        $params = $em->getConnection()->getParams();

        // Assert
        $this->assertEquals(FakeEntityManagerFactory::MARIADB_DATABASE_NAME, $this->getParam($params, 'dbname'));
    }
}
