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
    protected function setUp(): void
    {
        parent::setUp();
        (new FakeEntityManagerFactory())->cleanSqliteDatabase();
    }

    protected function tearDown(): void
    {
        (new FakeEntityManagerFactory())->cleanSqliteDatabase();
        parent::tearDown();
    }

    public function testCreateTenant(): void
    {
        // Arrange
        $em = (new FakeEntityManagerFactory())->createSqliteEntityManager();
        $tenantRepository = new TenantRepository($em);
        $tenant = new Tenant(new TenantId(), 'Campus26', 'user@campus26.com');

        // Act
        $tenantRepository->create($tenant);

        // Assert
        $this->assertFileExists(FakeEntityManagerFactory::sqliteDatabaseAbsolutePath());

        // Clean up tenant folder
        $fs = new Filesystem();
        $fs->remove(FakeEntityManagerFactory::projectRoot() . FakeEntityManagerFactory::DATA_PATH);
    }
}
