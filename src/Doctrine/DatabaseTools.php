<?php

namespace Phariscope\MultiTenant\Doctrine;

use Doctrine\DBAL\Connection;
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
}
