<?php

namespace Phariscope\MultiTenant\Tests\Doctrine;

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
}
