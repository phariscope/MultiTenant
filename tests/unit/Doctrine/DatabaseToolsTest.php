<?php

namespace Phariscope\MultiTenant\Tests\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\Exception as PdoDriverException;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\ORM\EntityManagerInterface;
use Mockery;
use Phariscope\MultiTenant\Doctrine\DatabaseTools;
use Phariscope\MultiTenant\Doctrine\Tools\ParamsConnection;
use Phariscope\MultiTenant\Tests\Doctrine\Tools\FakeEntityManagerFactory;
use PHPUnit\Framework\TestCase;

use function SafePHP\strval;

class DatabaseToolsTest extends TestCase
{
    private const MARIADB_SKIP_MESSAGE = <<<'MSG'
MariaDB is not reachable at host "mariadb:3306" (required for MySQL/MariaDB coverage).
Start the service, then re-run the tests:

  docker compose up -d mariadb
  bin/phpunit tests/unit/Doctrine/DatabaseToolsTest.php
MSG;

    protected function setUp(): void
    {
        parent::setUp();
        (new FakeEntityManagerFactory())->cleanSqliteDatabase();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testCreateSqliteDatabase(): void
    {
        // Arrange
        $em = (new FakeEntityManagerFactory())->createSqliteEntityManager();
        $sut = new DatabaseTools();

        // Act
        $sut->createDatabase($em);

        // Assert
        $params = $em->getConnection()->getParams();
        $path = strval(ParamsConnection::getParam($params, 'path'));
        $this->assertStringEndsWith(FakeEntityManagerFactory::SQLITE_DATABASE_PATH, $path);
    }

    public function testCreateSchema(): void
    {
        // Arrange
        $em = (new FakeEntityManagerFactory())->createSqliteEntityManager();
        $sut = new DatabaseTools();

        // Act
        $sut->createDatabase($em);
        $sut->createSchema($em);

        // Assert
        $this->assertTrue($em->getConnection()->createSchemaManager()->tablesExist(['entities']));
    }

    public function testSqliteDatabaseExists(): void
    {
        // Arrange
        $em = (new FakeEntityManagerFactory())->createSqliteEntityManager();
        $sut = new DatabaseTools();

        // Act
        $sut->createDatabase($em);

        // Assert
        $this->assertTrue($sut->databaseExists($em));
    }

    public function testDatabaseDrop(): void
    {
        // Arrange
        $em = (new FakeEntityManagerFactory())->createSqliteEntityManager();
        $sut = new DatabaseTools();

        // Act
        $sut->createDatabase($em);
        $sut->dropDatabase($em);

        // Assert
        $this->assertFalse($sut->databaseExists($em));
    }

    public function testCreateDatabaseSqliteIfNotExists(): void
    {
        // Arrange
        $em = (new FakeEntityManagerFactory())->createSqliteEntityManager();
        $sut = new DatabaseTools();

        // Act
        $sut->createDatabaseIfNotExists($em);

        // Assert
        $params = $em->getConnection()->getParams();
        $path = strval(ParamsConnection::getParam($params, 'path'));
        $this->assertStringEndsWith(FakeEntityManagerFactory::SQLITE_DATABASE_PATH, $path);
    }

    public function testUpdateSchema(): void
    {
        // Arrange
        $em = (new FakeEntityManagerFactory())->createSqliteEntityManager();
        $sut = new DatabaseTools();

        // Act
        $sut->createDatabase($em);
        $sut->updateSchema($em);

        // Assert
        $schemaManager = $em->getConnection()->createSchemaManager();
        $this->assertTrue($schemaManager->tablesExist(['entities']));
        $columns = $schemaManager->listTableColumns('entities');
        $this->assertArrayHasKey('id', $columns);
        $this->assertArrayHasKey('name', $columns);
    }

    public function testGetUpdateSchemaSql(): void
    {
        // Arrange
        $em = (new FakeEntityManagerFactory())->createSqliteEntityManager();
        $sut = new DatabaseTools();

        // Act
        $sut->createDatabase($em);
        $sqls = $sut->getUpdateSchemaSql($em);

        // Assert
        $this->assertNotEmpty($sqls);
        foreach ($sqls as $sql) {
            $this->assertIsString($sql);
            $this->assertNotSame('', trim($sql));
        }
        $joined = implode(' ', $sqls);
        $this->assertStringContainsString('entities', $joined);
        $this->assertStringContainsStringIgnoringCase('CREATE', $joined);
    }

    public function testCreateSqliteDatabaseThrowsWhenFileAlreadyExists(): void
    {
        // Arrange
        $em = (new FakeEntityManagerFactory())->createSqliteEntityManager();
        $sut = new DatabaseTools();
        $sut->createDatabase($em);

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('already exists');
        $sut->createDatabase($em);
    }

    public function testDatabaseExistsReturnsFalseWhenConnectionFails(): void
    {
        // Arrange
        $driverException = PdoDriverException::new(new \PDOException('Connection refused'));

        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->method('listDatabases')
            ->willThrowException(new ConnectionException($driverException, null));

        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());
        $connection->method('getParams')->willReturn(['dbname' => 'test']);
        $connection->method('createSchemaManager')->willReturn($schemaManager);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);
        $sut = new DatabaseTools();

        // Act
        $exists = $sut->databaseExists($em);

        // Assert
        $this->assertFalse($exists);
    }

    public function testDatabaseExistsReturnsTrueForNonSqliteWhenConnectionWorks(): void
    {
        // Arrange
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->method('listDatabases')->willReturn(['mydbname']);

        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());
        $connection->method('getParams')->willReturn(['dbname' => 'mydbname']);
        $connection->method('createSchemaManager')->willReturn($schemaManager);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);
        $sut = new DatabaseTools();

        // Act
        $exists = $sut->databaseExists($em);

        // Assert
        $this->assertTrue($exists);
    }

    public function testCreateMysqlDatabase(): void
    {
        // Arrange
        $this->skipIfMariaDbUnavailable();
        (new FakeEntityManagerFactory())->cleanMariadbDatabase();
        $em = (new FakeEntityManagerFactory())->createMariadbEntityManager();
        $sut = new DatabaseTools();

        // Act
        $sut->createDatabase($em);
        $params = $em->getConnection()->getParams();

        // Assert
        $this->assertSame(FakeEntityManagerFactory::MARIADB_DATABASE_NAME, $params['dbname'] ?? null);
        $this->assertTrue($sut->databaseExists($em));
    }

    public function testMysqlDatabaseDrop(): void
    {
        // Arrange
        $this->skipIfMariaDbUnavailable();
        (new FakeEntityManagerFactory())->cleanMariadbDatabase();
        $em = (new FakeEntityManagerFactory())->createMariadbEntityManager();
        $sut = new DatabaseTools();
        $sut->createDatabase($em);

        // Act
        $sut->dropDatabase($em);

        // Assert
        $this->assertFalse($sut->databaseExists($em));
    }

    private function skipIfMariaDbUnavailable(): void
    {
        if (!(new FakeEntityManagerFactory())->isMariaDbReachable()) {
            $this->markTestSkipped(self::MARIADB_SKIP_MESSAGE);
        }
    }
}
