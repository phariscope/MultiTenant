<?php

namespace Phariscope\MultiTenant\Tests\Integration;

use Phariscope\MultiTenant\DataFolder;
use Phariscope\MultiTenant\Doctrine\DatabaseTools;
use Phariscope\MultiTenant\Doctrine\EntityManagerResolver;
use Phariscope\MultiTenant\Tests\Doctrine\Tools\FakeEntityManagerFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Test d'intégration TDD : Vérifier que l'ensemble du workflow correspond au README "How it works"
 */
class MultiTenantIntegrationTest extends TestCase
{
    private Filesystem $filesystem;
    private string $tempDataPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filesystem = new Filesystem();
        $this->tempDataPath = sys_get_temp_dir() . '/multitenant_test_' . uniqid();

        // Nettoyer avant chaque test
        $this->cleanUpTestData();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->cleanUpTestData();
    }

    private function cleanUpTestData(): void
    {
        if ($this->filesystem->exists($this->tempDataPath)) {
            $this->filesystem->remove($this->tempDataPath);
        }
    }

    /**
     * Helper pour créer un EntityManager avec les variables d'environnement actuelles
     */
    private function createEntityManagerFromEnv(): \Doctrine\ORM\EntityManagerInterface
    {
        $dataPath = $_ENV["DATA_PATH"];
        $databaseUrl = $_ENV["DATABASE_URL"];

        // Extraire le nom du fichier de base de données depuis DATABASE_URL
        $databaseFileName = 'database.sqlite';
        if (preg_match('/\/([^\/]+\.sqlite)$/', $databaseUrl, $matches)) {
            $databaseFileName = $matches[1];
        }

        // Construire le chemin complet selon DATABASE_URL
        $databasePath = $dataPath . "/database/" . $databaseFileName;

        $connection = \Doctrine\DBAL\DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'path' => $databasePath,
        ]);

        $paths = [
            __DIR__ . '/../unit/Doctrine/Tools/resources/mapping',
        ];
        $config = \Doctrine\ORM\ORMSetup::createXMLMetadataConfiguration($paths, true);
        $config->setProxyDir(__DIR__ . '/../../var/proxies');
        $config->setProxyNamespace('Proxies');

        return new \Doctrine\ORM\EntityManager($connection, $config);
    }

    /**
     * Test TDD : Vérifier que la structure de dossiers est créée selon le README
     * DATA_PATH/tenants/tenantID1234/database/mydatabase.sqlite
     */
    public function testTenantFolderStructureAccordingToReadme(): void
    {
        // Simuler les variables d'environnement selon le README
        $_ENV["DATA_PATH"] = $this->tempDataPath;
        $_ENV["DATABASE_URL"] = "sqlite:///%DATA_PATH%/database/mydatabase.sqlite";

        $tenantId = "tenantID1234";
        $dataFolder = new DataFolder();

        // Vérifier que le dossier de données du tenant est correct
        $expectedTenantDataFolder = $this->tempDataPath . "/tenants/tenantID1234";
        $actualTenantDataFolder = $dataFolder->getTenantDataFolder($tenantId);
        $this->assertEquals($expectedTenantDataFolder, $actualTenantDataFolder);

        // Vérifier que le dossier de base de données est correct
        $expectedDatabaseFolder = $this->tempDataPath . "/tenants/tenantID1234/database";
        $actualDatabaseFolder = $dataFolder->getTenantDatabaseFolder($tenantId);
        $this->assertEquals($expectedDatabaseFolder, $actualDatabaseFolder);

        // Vérifier que le chemin complet de la base de données est correct
        $expectedDatabasePath = $this->tempDataPath . "/tenants/tenantID1234/database/mydatabase.sqlite";
        $actualDatabasePath = $dataFolder->getTenantDatabasePath($tenantId);
        $this->assertEquals($expectedDatabasePath, $actualDatabasePath);
    }

    /**
     * Test TDD : Vérifier que le workflow complet de création de tenant fonctionne
     */
    public function testCompleteWorkflowForTenantCreation(): void
    {
        // Simuler les variables d'environnement selon le README
        $_ENV["DATA_PATH"] = $this->tempDataPath;
        $_ENV["DATABASE_URL"] = "sqlite:///%DATA_PATH%/database/mydatabase.sqlite";

        $tenantId = "tenantID1234";

        // Créer un EntityManager de base avec les variables d'environnement actuelles
        $baseEntityManager = $this->createEntityManagerFromEnv();

        // Utiliser EntityManagerResolver pour obtenir un EntityManager pour le tenant
        $resolver = new EntityManagerResolver($baseEntityManager);
        $tenantEntityManager = $resolver->getEntityManager($tenantId);

        // Créer la base de données et le schéma pour le tenant
        $databaseTools = new DatabaseTools();
        $databaseTools->createDatabaseIfNotExists($tenantEntityManager);

        // Vérifier que le fichier de base de données a été créé au bon endroit
        $expectedDatabasePath = $this->tempDataPath . "/tenants/tenantID1234/database/mydatabase.sqlite";
        $this->assertTrue(
            file_exists($expectedDatabasePath),
            "Database file should exist at: " . $expectedDatabasePath
        );

        // Vérifier que la structure de dossiers est créée
        $expectedTenantFolder = $this->tempDataPath . "/tenants/tenantID1234";
        $expectedDatabaseFolder = $this->tempDataPath . "/tenants/tenantID1234/database";

        $this->assertTrue(is_dir($expectedTenantFolder), "Tenant folder should exist: " . $expectedTenantFolder);
        $this->assertTrue(is_dir($expectedDatabaseFolder), "Database folder should exist: " . $expectedDatabaseFolder);
    }

    /**
     * Test TDD : Vérifier que plusieurs tenants peuvent coexister
     */
    public function testMultipleTenantsCoexistence(): void
    {
        // Simuler les variables d'environnement selon le README
        $_ENV["DATA_PATH"] = $this->tempDataPath;
        $_ENV["DATABASE_URL"] = "sqlite:///%DATA_PATH%/database/mydatabase.sqlite";

        $tenant1Id = "tenant1";
        $tenant2Id = "tenant2";

        // Créer un EntityManager de base avec les variables d'environnement actuelles
        $baseEntityManager = $this->createEntityManagerFromEnv();

        // Créer les EntityManagers pour chaque tenant
        $resolver = new EntityManagerResolver($baseEntityManager);
        $tenant1EntityManager = $resolver->getEntityManager($tenant1Id);
        $tenant2EntityManager = $resolver->getEntityManager($tenant2Id);

        // Créer les bases de données pour chaque tenant
        $databaseTools = new DatabaseTools();
        $databaseTools->createDatabaseIfNotExists($tenant1EntityManager);
        $databaseTools->createDatabaseIfNotExists($tenant2EntityManager);

        // Vérifier que les deux bases de données existent dans des dossiers séparés
        $expectedDatabase1Path = $this->tempDataPath . "/tenants/tenant1/database/mydatabase.sqlite";
        $expectedDatabase2Path = $this->tempDataPath . "/tenants/tenant2/database/mydatabase.sqlite";

        $this->assertTrue(file_exists($expectedDatabase1Path), "Tenant 1 database should exist");
        $this->assertTrue(file_exists($expectedDatabase2Path), "Tenant 2 database should exist");

        // Vérifier que les dossiers sont bien séparés
        $this->assertTrue(is_dir($this->tempDataPath . "/tenants/tenant1"), "Tenant 1 folder should exist");
        $this->assertTrue(is_dir($this->tempDataPath . "/tenants/tenant2"), "Tenant 2 folder should exist");
    }

    /**
     * Test TDD : Vérifier que la structure correspond exactement au README
     */
    public function testReadmeExampleStructure(): void
    {
        // Exemple exact du README :
        // DATA_PATH=./var/data
        // DATABASE_URL=sqlite:///%DATA_PATH%/database/mydatabase.sqlite
        // Given the tenant "tenantID1234", the database create command will create the following file:
        // ./var/data/tenants/tenantID1234/database/mydatabase.sqlite

        $_ENV["DATA_PATH"] = "./var/data";
        $_ENV["DATABASE_URL"] = "sqlite:///%DATA_PATH%/database/mydatabase.sqlite";

        $tenantId = "tenantID1234";
        $dataFolder = new DataFolder();

        // Vérifier que le chemin généré correspond exactement à l'exemple du README
        $expectedPath = "./var/data/tenants/tenantID1234/database/mydatabase.sqlite";
        $actualPath = $dataFolder->getTenantDatabasePath($tenantId);

        $this->assertEquals($expectedPath, $actualPath);
    }
}
