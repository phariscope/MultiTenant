<?php

namespace Phariscope\MultiTenant\Infrastructure\Persistence\Tenant;

use Doctrine\ORM\EntityManagerInterface;
use Phariscope\MultiTenant\Doctrine\DatabaseTools;
use Phariscope\MultiTenant\Domain\Model\Tenant\Tenant;
use Phariscope\MultiTenant\Domain\Model\Tenant\TenantRepositoryInterface;

class TenantRepository implements TenantRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function create(Tenant $tenant): void
    {
        $databaseTools = new DatabaseTools();
        $databaseTools->createDatabase($this->entityManager);
        $databaseTools->createSchema($this->entityManager);
    }
}
