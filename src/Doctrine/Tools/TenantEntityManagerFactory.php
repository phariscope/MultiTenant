<?php

namespace Phariscope\MultiTenant\Doctrine\Tools;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Phariscope\MultiTenant\Doctrine\Sqlite\PathTransformer;
use Symfony\Component\Filesystem\Filesystem;

use function SafePHP\strval;

/**
 * @psalm-import-type Params from DriverManager
 */
class TenantEntityManagerFactory
{
    public function createSqliteEntityManager(
        EntityManagerInterface $em,
        string $tenantId,
        Filesystem $filesystem = new Filesystem()
    ): EntityManager {
        $connection = $em->getConnection();

        $params = $connection->getParams();

        $driver =  ParamsConnection::getParam($params, 'driver');
        if (isset($params['memory']) && $params['memory']) {
            throw new \InvalidArgumentException('SQlite Memory database is not supported');
        }

        $path =  strval(ParamsConnection::getParam($params, 'path'));

        /** @psalm-var Params $p */
        $p =             [
            'driver' => $driver,
            'path' => (new PathTransformer())->transform($path, $tenantId),
        ];
        $newConnection = DriverManager::getConnection(
            $p,
            $em->getConnection()->getConfiguration()
        );
        return new EntityManager($newConnection, $em->getConfiguration());
    }

    public function createMariadbEntityManager(EntityManagerInterface $em, string $tenantId): EntityManager
    {
        $connection = $em->getConnection();
        $newConnection = $this->makeConnection($connection, $tenantId);

        return new EntityManager($newConnection, $em->getConfiguration());
    }


    private function makeConnection(Connection $connection, string $tenantId): Connection
    {
        $params = $connection->getParams();
        /** @psalm-var Params $connectionParams */
        $connectionParams = [
            'driver' => ParamsConnection::getParam($params, 'driver'),
            'host' => ParamsConnection::getParam($params, 'host'),
            'port' => ParamsConnection::getParam($params, 'port'),
            'dbname' => $this->makeTenantDbname(
                strval(ParamsConnection::getParam($params, 'dbname')),
                $tenantId
            ),
            'user' => ParamsConnection::getParam($params, 'user'),
            'password' => ParamsConnection::getParam($params, 'password'),
            'serverVersion' => ParamsConnection::getParam($params, 'serverVersion'),
            'charset' => ParamsConnection::getParam($params, 'charset'),
        ];
        return DriverManager::getConnection(
            $connectionParams,
            $connection->getConfiguration()
        );
    }

    private function makeTenantDbname(string $dbname, string $tenantId): string
    {
        return sprintf("%s_%s", $dbname, $tenantId);
    }
}
