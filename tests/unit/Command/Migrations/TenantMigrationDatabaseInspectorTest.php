<?php

declare(strict_types=1);

namespace Phariscope\MultiTenant\Tests\Command\Migrations;

use Phariscope\MultiTenant\Command\Migrations\TenantMigrationDatabaseInspector;
use Phariscope\MultiTenant\Tests\Share\IsolatesDataPathEnvTrait;
use PHPUnit\Framework\TestCase;

final class TenantMigrationDatabaseInspectorTest extends TestCase
{
    use IsolatesDataPathEnvTrait;

    /** @var mixed */
    private $savedDatabaseUrl;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpIsolatedWritableDataPath();
        $this->savedDatabaseUrl = $_ENV['DATABASE_URL'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->savedDatabaseUrl === null) {
            unset($_ENV['DATABASE_URL']);
        } else {
            $_ENV['DATABASE_URL'] = $this->savedDatabaseUrl;
        }
        $this->tearDownIsolatedDataPath();
        parent::tearDown();
    }

    public function testResolvePathWhenContextIsAlreadyTenantScoped(): void
    {
        // Arrange
        $root = $this->getIsolatedDataPathDir();
        $tenantId = 'cl_demo10_yve5d6q';
        $databaseFile = $root . '/tenants/' . $tenantId . '/database/captain-learning.sqlite';
        mkdir(dirname($databaseFile), 0775, true);
        touch($databaseFile);

        $_ENV['DATA_PATH'] = $root . '/tenants/' . $tenantId;
        $_ENV['DATABASE_URL'] = 'sqlite:///' . $root . '/tenants/' . $tenantId
            . '/database/captain-learning.sqlite';

        $inspector = new TenantMigrationDatabaseInspector();

        // Act
        $resolved = $inspector->resolveTenantDatabasePath($tenantId);

        // Assert
        $this->assertSame($databaseFile, $resolved);
        $this->assertTrue($inspector->tenantDatabaseExists($tenantId));
    }
}
