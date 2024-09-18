<?php

namespace Phariscope\MultiTenant\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\PDOException;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\ORM\EntityManagerInterface;
use Phariscope\MultiTenant\Doctrine\Tools\ParamsConnection;

use function Safe\mkdir;
use function Safe\touch;
use function SafePHP\strval;

class DatabaseTools
{
    private const SQLITE_DIRECTORY_MODE = 0755;
    public function createDatabase(EntityManagerInterface $em): void
    {
        $connection = $em->getConnection();
        $driver = $connection->getDriver()->getDatabasePlatform();

        if ($driver instanceof SqlitePlatform) {
            $this->mkdirForSqlite($connection);
            return;
        }

        $this->createDatabaseWithNewName($connection);
    }

    private function mkdirForSqlite(Connection $connection): void
    {
        $params = $connection->getParams();
        $path = strval(ParamsConnection::getParam($params, 'path'));
        if (file_exists($path)) {
            throw new \RuntimeException("Database file '$path' already exists");
        }
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, self::SQLITE_DIRECTORY_MODE, true);
        }
        touch($path);
    }

    private function createDatabaseWithNewName(Connection $connection): void
    {
        $params = $connection->getParams();
        if (array_key_exists('dbname', $params)) {
            $dbname = $params['dbname'];
            unset($params['dbname']);
            $newConnection = new Connection($params, $connection->getDriver());
            $newConnection->createSchemaManager()->createDatabase($dbname);
        }
    }

    public function createSchema(EntityManagerInterface $em): void
    {
        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($em);
        $classes = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->createSchema($classes);
    }

    public function databaseExists(EntityManagerInterface $em): bool
    {
        $connection = $em->getConnection();
        $driver = $connection->getDriver()->getDatabasePlatform();
        if ($driver instanceof SqlitePlatform) {
            $params = $connection->getParams();
            $path = strval(ParamsConnection::getParam($params, 'path'));
            return file_exists($path);
        }

        $params = $connection->getParams();
        $schema = $connection->createSchemaManager();
        try {
            $schema->listDatabases();
        } catch (ConnectionException $e) {
            return false;
        }
        return true;
    }
}
