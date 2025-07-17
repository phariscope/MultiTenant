<?php

namespace Phariscope\MultiTenant\Tests\Infrastructure\Persistence\Tenant;

use Phariscope\MultiTenant\Domain\Model\Tenant\Tenant;
use Phariscope\MultiTenant\Domain\Model\Tenant\TenantId;
use Phariscope\MultiTenant\Infrastructure\Persistence\Tenant\TenantRepository;
use Phariscope\MultiTenant\Tests\Doctrine\Tools\FakeEntityManagerFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class TenantRepositoryTest extends TestCase
{
    public function testCreateTenant(): void
    {

        $em = (new FakeEntityManagerFactory())->createSqliteEntityManager();

        $tenantRepository = new TenantRepository($em);
        $tenant = new Tenant(new TenantId(), 'Campus26', 'user@campus26.com');
        $tenantRepository->create($tenant);

        $this->assertFileExists(getcwd() . FakeEntityManagerFactory::SQLITE_DATABASE_PATH);

       // clean tenant remove folder
        $fs = new Filesystem();
        $fs->remove(getcwd() . FakeEntityManagerFactory::DATA_PATH);
    }
}
