<?php

namespace Phariscope\MultiTenant\Tests\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\XmlDriver;
use Symfony\Component\Filesystem\Filesystem;

class FakeEntityManagerFactory
{
    public function cleanSqliteDatabase(): void
    {
        $fs = new Filesystem();
        $fs->remove(getcwd() . '/var/tmp/data');
    }

    public function createSqliteEntityManager(): EntityManager
    {
        $connection = DriverManager::getConnection(
            [
                'driver' => 'pdo_sqlite',
                'path' => getcwd() . '/var/tmp/data/database.sqlite',
            ]
        );
        return $this->createEntityManager($connection);
    }

    private function createEntityManager(Connection $connection): EntityManager
    {
        $config = new Configuration();
        $mappingDriver = new XmlDriver(
            [
                __DIR__ . '/../../../../../../src/Domain/Model',
            ]
        );
        $config->setMetadataDriverImpl(
            $mappingDriver
        );
        $config->setProxyDir(
            __DIR__ . '/../../../../../../var/proxies'
        );
        $config->setProxyNamespace('Proxies');
        return new EntityManager(
            $connection,
            $config
        );
    }
    public function createMariadbEntityManager(): EntityManager
    {
        $connection = DriverManager::getConnection(
            [
                'url' => 'mysql://user:password@mariadb:3306/mydbname?serverVersion=10.11.5-MariaDB&charset=utf8mb4'
            ]
        );
        return $this->createEntityManager($connection);
    }
}
