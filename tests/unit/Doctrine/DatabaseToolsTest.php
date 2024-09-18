<?php

namespace Phariscope\MultiTenant\Tests\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\AbstractMySQLDriver;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\XmlDriver;
use Phariscope\MultiTenant\Doctrine\DatabaseTools;
use Phariscope\MultiTenant\Doctrine\Tools\ParamsConnection;
use Phariscope\MultiTenant\Tests\Doctrine\Tools\FakeEntityManagerFactory;
use PHPUnit\Framework\TestCase;

use function SafePHP\strval;

class DatabaseToolsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new FakeEntityManagerFactory())->cleanSqliteDatabase();
    }

    public function testCreateSqliteDatabase(): void
    {
        $em = (new FakeEntityManagerFactory())->createSqliteEntityManager();
        $sut = new DatabaseTools();

        $sut->createDatabase($em);

        $params = $em->getConnection()->getParams();
        $path = strval(ParamsConnection::getParam($params, 'path'));
        $this->assertStringEndsWith(FakeEntityManagerFactory::SQLITE_DATABASE_PATH, $path);
    }
}
