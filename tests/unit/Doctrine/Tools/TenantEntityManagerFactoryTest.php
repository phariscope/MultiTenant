<?php

namespace Phariscope\MultiTenant\Tests\Doctrine\Tools;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Phariscope\MultiTenant\Doctrine\Tools\TenantEntityManagerFactory;
use PHPUnit\Framework\TestCase;

use function SafePHP\strval;

/**
 * @psalm-import-type Params from DriverManager
 */
class TenantEntityManagerFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new FakeEntityManagerFactory())->cleanSqliteDatabase();
    }

    public function testCreateSqliteEntityManager(): void
    {
        $em = (new FakeEntityManagerFactory())->createSqliteEntityManager();
        $sut = new TenantEntityManagerFactory();
        $result = $sut->createSqliteEntityManager($em, 'tenant123');

        $this->assertEquals(true, $result instanceof EntityManager);
        $params = $result->getConnection()->getParams();
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
        $sut = new TenantEntityManagerFactory();
        $result = $sut->createMariadbEntityManager($em, 'tenant123');

        $params = $result->getConnection()->getParams();
        $this->assertEquals(true, $result instanceof EntityManager);
        $this->assertEquals('mydbname_tenant123', $this->getParam($params, 'dbname'));
        $this->assertEquals('pdo_mysql', $this->getParam($params, 'driver'));
        $this->assertEquals('root', $this->getParam($params, 'user'));
        $this->assertEquals('password', $this->getParam($params, 'password'));
        $this->assertEquals('10.11.5-MariaDB', $this->getParam($params, 'serverVersion'));
        $this->assertEquals('utf8mb4', $this->getParam($params, 'charset'));
    }

    public function testSqliteMemoryException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SQlite Memory database is not supported');

        $em = (new FakeEntityManagerFactory())->createSqliteInMemoryEntityManager();
        $sut = new TenantEntityManagerFactory();
        $sut->createSqliteEntityManager($em, 'tenant123');
    }
/*
        public function testSqlitePathException(): void
        {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage("Unable to create directory 'not/existing/directory'");

        $em = (new FakeEntityManagerFactory())->createSqliteEntityManager();
        $sut = new TenantEntityManagerFactory();
        $sut->createSqliteEntityManager($em, 'tent*');
    }
        */
}
