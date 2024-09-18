<?php

namespace Phariscope\MultiTenant\Doctrine;

use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Phariscope\MultiTenant\Doctrine\Tools\TenantEntityManagerFactory;
use Phariscope\MultiTenant\Doctrine\Tools\TenantManager;
use Symfony\Component\HttpFoundation\Request;

class EntityManagerResolver
{
    private EntityManagerInterface $wrapped;

    public function __construct(EntityManagerInterface $wrapped)
    {
        $this->wrapped = $wrapped;
    }

    public function getEntityManager(?string $tenantId = null): EntityManagerInterface
    {
        $tenantId ??= $this->findTenantIdIfExist();
        if ($tenantId !== null) {
            return $this->createEntityManagerForTenant($tenantId);
        }
        return $this->wrapped;
    }

    private function findTenantIdIfExist(): ?string
    {
        $tenantManager = new TenantManager();
        return $tenantManager->getCurrentTenantId();
    }

    private function createEntityManagerForTenant(string $tenantId): EntityManager
    {
        $connection = $this->wrapped->getConnection();
        $driver = $connection->getDriver()->getDatabasePlatform();
        $factory = new TenantEntityManagerFactory();
        if ($driver instanceof SqlitePlatform) {
            return $factory->createSqliteEntityManager($this->wrapped, $tenantId);
        }
        return $factory->createMariadbEntityManager($this->wrapped, $tenantId);
    }

    public function getEntityManagerByRequest(Request $request): EntityManagerInterface
    {
        $tenantManager = new TenantManager($request);
        $tenantId = $tenantManager->getCurrentTenantId();
        return $this->getEntityManager($tenantId);
    }
}
