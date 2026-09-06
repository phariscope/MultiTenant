<?php

namespace Phariscope\MultiTenant\Tests\Doctrine\Tools;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\XmlDriver;
use Doctrine\ORM\ORMSetup;
use Symfony\Component\Filesystem\Filesystem;

class FakeEntityManagerFactory
{
    public const DATA_PATH = '/var/tmp/data/myApp';
    public const SQLITE_DATABASE_SUBPATH = 'subfolder/database.sqlite';
    public const SQLITE_DATABASE_PATH = self::DATA_PATH . '/' . self::SQLITE_DATABASE_SUBPATH;

    public const MARIADB_DATABASE_NAME = 'mydbname';

    public static function projectRoot(): string
    {
        return dirname(__DIR__, 4);
    }

    public static function sqliteDatabaseAbsolutePath(): string
    {
        return self::projectRoot() . self::SQLITE_DATABASE_PATH;
    }

    public function cleanSqliteDatabase(): void
    {
        $fs = new Filesystem();
        $fs->remove(self::projectRoot() . self::DATA_PATH);
    }

    public function createSqliteEntityManager(): EntityManager
    {
        $connection = DriverManager::getConnection(
            [
                'driver' => 'pdo_sqlite',
                'path' => self::sqliteDatabaseAbsolutePath(),
            ]
        );
        return $this->createEntityManager($connection);
    }

    private function createEntityManager(Connection $connection): EntityManager
    {

        $paths = [
            __DIR__ . '/resources/mapping',
        ];
        $config = ORMSetup::createXMLMetadataConfiguration($paths, true);
        $config->setProxyDir(
            __DIR__ . '/../../../../var/proxies'
        );
        $config->setProxyNamespace('Proxies');
        $em = new EntityManager(
            $connection,
            $config
        );
        $driverImpl = new XmlDriver([__DIR__ . '/resources/mapping']);
        $config->setMetadataDriverImpl($driverImpl);
        return $em;
    }

    public function createSqliteInMemoryEntityManager(): EntityManager
    {
        $connection = DriverManager::getConnection(
            [
                'driver' => 'pdo_sqlite',
                'memory' => true,
            ]
        );
        return $this->createEntityManager($connection);
    }

    public function createMariadbEntityManager(): EntityManager
    {
        $connection = DriverManager::getConnection(
            [
                'driver' => 'pdo_mysql',
                'host' => 'mariadb',
                'port' => 3306,
                'user' => 'root',
                'password' => 'password',
                'dbname' => self::MARIADB_DATABASE_NAME,
                'serverVersion' => '10.11.5-MariaDB',
                'charset' => 'utf8mb4',
            ]
        );
        return $this->createEntityManager($connection);
    }

    /**
     * Fast TCP probe (1s) — avoids long PDO timeouts when MariaDB is down.
     */
    public function isMariaDbReachable(): bool
    {
        /** @var bool|null $reachable */
        static $reachable = null;
        if ($reachable !== null) {
            return $reachable;
        }

        $errno = 0;
        $errstr = '';
        $socket = @fsockopen('mariadb', 3306, $errno, $errstr, 1);
        if (is_resource($socket)) {
            fclose($socket);
            $reachable = true;

            return true;
        }

        $reachable = false;

        return false;
    }

    public function cleanMariadbDatabase(): void
    {
        $connection = DriverManager::getConnection(
            [
                'driver' => 'pdo_mysql',
                'host' => 'mariadb',
                'port' => 3306,
                'user' => 'root',
                'password' => 'password',
            ]
        );
        $schemaManager = $connection->createSchemaManager();
        $databases = $schemaManager->listDatabases();
        if (in_array('mydbname', $databases)) {
            $schemaManager->dropDatabase('mydbname');
        }
    }
}
