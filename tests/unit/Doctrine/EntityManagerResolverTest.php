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
    private ?string $savedEnvDataPath = null;

    protected function setUp(): void
    {
        parent::setUp();
        (new FakeEntityManagerFactory())->cleanSqliteDatabase();
        if (isset($_REQUEST['tenant_id'])) {
            unset($_REQUEST['tenant_id']);
        }

        if (isset($_ENV['DATA_PATH'])) {
            $this->savedEnvDataPath = $_ENV['DATA_PATH'];
        }
        $_ENV['DATA_PATH'] = '../data/myApp';
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

    public function testGetEntityManagerByDefault(): void
    {
        // Arrange
        $em = (new FakeEntityManagerFactory())->createSqliteEntityManager();
        $sut = new EntityManagerResolver($em);

        // Act
        $result = $sut->getEntityManager();

        // Assert
        $this->assertEquals($em, $result);

        $params = $result->getConnection()->getParams();
        $this->assertStringEndsWith(
            FakeEntityManagerFactory::SQLITE_DATABASE_PATH,
            strval(ParamsConnection::getParam($params, 'path'))
        );
    }

    public function testGetEntityManagerWithTenant(): void
    {
        // Arrange
        $em = (new FakeEntityManagerFactory())->createSqliteEntityManager();
        $sut = new EntityManagerResolver($em);

        // Act
        $result = $sut->getEntityManager('tenant123');

        // Assert
        $params = $result->getConnection()->getParams();
        $this->assertStringEndsWith(
            'tenants/tenant123/' . FakeEntityManagerFactory::SQLITE_DATABASE_SUBPATH,
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
            'tenants/tenant123/subfolder/database.sqlite',
            strval(ParamsConnection::getParam($params, 'path'))
        );
    }
}
