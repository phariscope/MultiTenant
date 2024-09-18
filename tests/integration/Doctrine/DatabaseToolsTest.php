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
        $em = (new FakeEntityManagerFactory())->createMariadbEntityManager();
        $sut = new DatabaseTools();
        $sut->createDatabase($em);
        $params = $em->getConnection()->getParams();
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
        $em = (new FakeEntityManagerFactory())->createMariadbEntityManager();
        $sut = new DatabaseTools();

        $sut->createDatabase($em);
        $this->assertTrue($sut->databaseExists($em));
    }

    public function testDatabaseDoesNotExist(): void
    {
        $em = (new FakeEntityManagerFactory())->createMariadbEntityManager();
        $sut = new DatabaseTools();

        $this->assertFalse($sut->databaseExists($em));
    }
}
