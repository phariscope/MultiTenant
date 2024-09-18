<?php

namespace Phariscope\MultiTenant\Tests\Doctrine;

use Doctrine\DBAL\DriverManager;
use Phariscope\MultiTenant\Doctrine\EntityManagerResolver;
use Phariscope\MultiTenant\Doctrine\Tools\ParamsConnection;
use Phariscope\MultiTenant\Tests\Doctrine\Tools\FakeEntityManagerFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

use function SafePHP\strval;

/**
 * @psalm-import-type Params from DriverManager
 */
class EntityManagerResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new FakeEntityManagerFactory())->cleanSqliteDatabase();
        if (isset($_REQUEST['tenant_id'])) {
            unset($_REQUEST['tenant_id']);
        }
    }

    public function testGetEntityManagerByDefault(): void
    {
        if (isset($_REQUEST['tenant_id'])) {
            unset($_REQUEST['tenant_id']);
        }
        $em = (new FakeEntityManagerFactory())->createSqliteEntityManager();
        $sut = new EntityManagerResolver($em);

        $result = $sut->getEntityManager();

        $this->assertEquals($em, $result);
        $params = $result->getConnection()->getParams();
        $this->assertStringEndsWith(
            'data/database.sqlite',
            strval(ParamsConnection::getParam($params, 'path'))
        );
    }

    public function testGetEntityManagerWithTenant(): void
    {
        $em = (new FakeEntityManagerFactory())->createSqliteEntityManager();
        $sut = new EntityManagerResolver($em);

        $result = $sut->getEntityManager('tenant123');

        $params = $result->getConnection()->getParams();
        $this->assertStringEndsWith(
            'databases/tenant123/database.sqlite',
            strval(ParamsConnection::getParam($params, 'path'))
        );
    }

    public function testAnotherDriverThanSQLite(): void
    {
        $em = (new FakeEntityManagerFactory())->createMariadbEntityManager();

        $sut = new EntityManagerResolver($em);

        $result = $sut->getEntityManager('tenant123');
        $params = $result->getConnection()->getParams();
        $this->assertEquals('mydbname_tenant123', ParamsConnection::getParam($params, 'dbname'));
    }

    public function testGetEntityManagerByRequest(): void
    {
        $request = new Request(['tenant_id' => 'tenant123']);
        $em = (new FakeEntityManagerFactory())->createSqliteEntityManager();
        $sut = new EntityManagerResolver($em);

        $result = $sut->getEntityManagerByRequest($request);

        $params = $result->getConnection()->getParams();
        $this->assertStringEndsWith(
            'databases/tenant123/database.sqlite',
            strval(ParamsConnection::getParam($params, 'path'))
        );
    }
}
