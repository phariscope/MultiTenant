<?php

namespace Phariscope\MultiTenant\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\ORM\Decorator\EntityManagerDecorator;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;

use function SafePHP\strval;

/**
 * @psalm-import-type Params from DriverManager
 */
class MultiTenantEntityManager extends EntityManagerDecorator
{
    private const SQLITE_TENANT_DATABSES_SUB_PATH = 'databases';

    public function isUninitializedObject(mixed $value): bool
    {
        return $this->wrapped->isUninitializedObject($value);
    }

    public function create(?string $tenantId = null): EntityManagerInterface
    {
        $tenantId = $this->autodetectTenantId($tenantId);
        if ($tenantId === null) {
            return $this->wrapped;
        }
        $connection = $this->wrapped->getConnection();
        $driver = $connection->getDriver()->getDatabasePlatform();
        if ($driver instanceof SqlitePlatform) {
            return $this->createSqliteEntityManager($connection, $tenantId);
        }
        if ($driver instanceof MySQLPlatform) {
            return $this->createMysqlEntityManager($connection, $tenantId);
        }
        throw new \InvalidArgumentException('Unsupported driver');
    }

    private function autodetectTenantId(?string $tenantId): ?string
    {
        if ($tenantId !== null) {
            return $tenantId;
        }
        if (isset($_REQUEST['tenant_id'])) {
            return $_REQUEST['tenant_id'];
        }
        return null;
    }

    private function createSqliteEntityManager(Connection $connection, string $tenantId): EntityManagerInterface
    {

        $params = $connection->getParams();

        $driver =  $this->getParam($params, 'driver');
        if (isset($params['memory']) && $params['memory']) {
            $path = ':memory:';
        } else {
            $path =  strval($this->getParam($params, 'path'));
        }

        /** @psalm-var Params $p */
        $p =             [
            'driver' => $driver,
            'path' => $this->transformPath($path, $tenantId)
        ];

        $newConnection = DriverManager::getConnection(
            $p,
            $this->wrapped->getConnection()->getConfiguration()
        );
        return new EntityManager($newConnection, $this->wrapped->getConfiguration());
    }

    private function transformPath(string $path, string $tenantId): string
    {
        $pathParts = explode('/', $path);
        array_splice($pathParts, -1, 0, [self::SQLITE_TENANT_DATABSES_SUB_PATH, $tenantId]);
        $path = implode('/', $pathParts);
        $directory = dirname($path);
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true)) {
                throw new \RuntimeException("Unable to create directory '$directory'");
            }
        }
        return $path;
    }

    private function createMysqlEntityManager(Connection $connection, string $tenantId): EntityManagerInterface
    {
        $newConnection = $this->makeConnection($connection, $tenantId);

        return new EntityManager($newConnection, $this->wrapped->getConfiguration());
    }


    private function makeConnection(Connection $connection, string $tenantId): Connection
    {
        $params = $connection->getParams();
        /** @psalm-var Params $connectionParams */
        $connectionParams = [
            'driver' => $this->getParam($params, 'driver'),
            'host' => $this->getParam($params, 'host'),
            'port' => $this->getParam($params, 'port'),
            'dbname' => $this->makeTenantDbname(strval($this->getParam($params, 'dbname')), $tenantId),
            'user' => $this->getParam($params, 'user'),
            'password' => $this->getParam($params, 'password'),
            'serverVersion' => $this->getParam($params, 'serverVersion'),
            'charset' => $this->getParam($params, 'charset'),
        ];
        return DriverManager::getConnection(
            $connectionParams,
            $this->wrapped->getConnection()->getConfiguration()
        );
    }

    private function makeTenantDbname(string $dbname, string $tenantId): string
    {
        return sprintf("%s_%s", $dbname, $tenantId);
    }

    /**
     * @psalm-param Params $params
     */
    private function getParam(array $params, string $key): mixed
    {
        if (isset($params[$key])) {
            return $params[$key];
        }

        throw new \InvalidArgumentException(ucfirst($key) . ' not found');
    }

    public function createDatabase(string $tenantId): void
    {
        $connection = $this->wrapped->getConnection();
        $driver = $connection->getDriver()->getDatabasePlatform();
        if ($driver instanceof SqlitePlatform) {
            $this->createSqliteDatabase($connection, $tenantId);
        }
        if ($driver instanceof MySQLPlatform) {
            $this->createMysqlDatabase($connection, $tenantId);
        }
    }

    private function createSqliteDatabase(Connection $connection, string $tenantId): void
    {
        $params = $connection->getParams();
        $path = strval($this->getParam($params, 'path'));
        $path = $this->transformPath($path, $tenantId);

        if (file_exists($path)) {
            throw new \RuntimeException("Database file '$path' already exists");
        } else {
            if (!touch($path)) {
                throw new \RuntimeException("Unable to create file '$path'");
            }
        }
    }

    private function createMysqlDatabase(Connection $connection, string $tenantId): void
    {
        $dbname = $this->makeTenantDbname(strval($connection->getDatabase()), $tenantId);
        $connection->createSchemaManager()->createDatabase($dbname);
    }
}
