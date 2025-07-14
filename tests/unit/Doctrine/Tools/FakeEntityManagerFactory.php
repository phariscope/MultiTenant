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

    public function cleanSqliteDatabase(): void
    {
        $fs = new Filesystem();
        $fs->remove(getcwd() . self::DATA_PATH);
    }

    public function createSqliteEntityManager(): EntityManager
    {
        $connection = DriverManager::getConnection(
            [
                'driver' => 'pdo_sqlite',
                'path' => getcwd() . self::SQLITE_DATABASE_PATH,
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
        $url = sprintf(
            'mysql://root:password@mariadb:3306/%s?serverVersion=10.11.5-MariaDB&charset=utf8mb4',
            self::MARIADB_DATABASE_NAME
        );
        $connection = DriverManager::getConnection(
            [
                'url' => $url
            ]
        );
        return $this->createEntityManager($connection);
    }

    public function cleanMariadbDatabase(): void
    {
        $connection = DriverManager::getConnection(
            [
                'url' => 'mysql://root:password@mariadb:3306'
            ]
        );
        $schemaManager = $connection->createSchemaManager();
        $databases = $schemaManager->listDatabases();
        if (in_array('mydbname', $databases)) {
            $schemaManager->dropDatabase('mydbname');
        }
    }
}
