<?php

namespace Phariscope\MultiTenant\Tests\Doctrine;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Phariscope\MultiTenant\Doctrine\MultiTenantEntityManager;
use PHPUnit\Framework\TestCase;

use function SafePHP\strval;

/**
 * @psalm-import-type Params from DriverManager
 */
class MultiTenantEntityManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new FakeEntityManagerFactory())->cleanSqliteDatabase();
    }

    public function testCreateTenantSqliteEntityManager(): void
    {
        $em = (new FakeEntityManagerFactory())->createSqliteEntityManager();
        $sut = (new MultiTenantEntityManager($em))->create('tenant123');
        $this->assertEquals(true, $sut instanceof EntityManager);
        $params = $sut->getConnection()->getParams();
        $this->assertStringEndsWith('databases/tenant123/database.sqlite', strval($this->getParam($params, 'path')));
        $this->assertEquals('pdo_sqlite', $this->getParam($params, 'driver'));
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

    public function testCreateTenantMariadbEntityManager(): void
    {
        $em = (new FakeEntityManagerFactory())->createMariadbEntityManager();
        $sut = (new MultiTenantEntityManager($em))->create('tenant123');
        $params = $sut->getConnection()->getParams();
        $this->assertEquals(true, $sut instanceof EntityManager);
        $this->assertEquals('mydbname_tenant123', $this->getParam($params, 'dbname'));
        $this->assertEquals('pdo_mysql', $this->getParam($params, 'driver'));
        $this->assertEquals('user', $this->getParam($params, 'user'));
        $this->assertEquals('password', $this->getParam($params, 'password'));
        $this->assertEquals('10.11.5-MariaDB', $this->getParam($params, 'serverVersion'));
        $this->assertEquals('utf8mb4', $this->getParam($params, 'charset'));
    }

    public function testCreateTenantDatabase(): void
    {
        $em = (new FakeEntityManagerFactory())->createSqliteEntityManager();
        $sut = new MultiTenantEntityManager($em);
        $sut->createDatabase('tenant123');
        $this->assertFileExists(getcwd() . '/var/tmp/data/databases/tenant123/database.sqlite');
    }

    public function testCreateTenantDatabaseWithAutdodectingTenant(): void
    {
        $em = (new FakeEntityManagerFactory())->createSqliteEntityManager();
        $_REQUEST['tenant_id'] = 'tenant321';
        $sut = (new MultiTenantEntityManager($em))->create();
        $this->assertEquals(true, $sut instanceof EntityManager);
        $params = $sut->getConnection()->getParams();
        $this->assertStringEndsWith('databases/tenant321/database.sqlite', strval($this->getParam($params, 'path')));
        $this->assertEquals('pdo_sqlite', $this->getParam($params, 'driver'));
    }
}
