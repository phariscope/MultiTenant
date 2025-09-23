<?php

namespace Phariscope\MultiTenant\Tests;

use Phariscope\MultiTenant\ContextTransformer;
use PHPUnit\Framework\TestCase;

class ContextTransformerTest extends TestCase
{
    private ?string $originalDatabaseUrl;
    private ?string $originalDataPath;
    private ?string $originalHttpTenantId;
    protected function setUp(): void
    {
        // sauvegarde les variables d'environnement
        $this->originalDatabaseUrl = isset($_ENV['DATABASE_URL']) ? $_ENV['DATABASE_URL'] : null;
        $this->originalDataPath = isset($_ENV['DATA_PATH']) ? $_ENV['DATA_PATH'] : null;
        $this->originalHttpTenantId = isset($_SERVER['HTTP_X_TENANT_ID']) ? $_SERVER['HTTP_X_TENANT_ID'] : null;
    }

    protected function tearDown(): void
    {
        if ($this->originalDatabaseUrl !== null) {
            $_ENV['DATABASE_URL'] = $this->originalDatabaseUrl;
        } else {
            unset($_ENV['DATABASE_URL']);
        }
        if ($this->originalDataPath !== null) {
            $_ENV['DATA_PATH'] = $this->originalDataPath;
        } else {
            unset($_ENV['DATA_PATH']);
        }
        if ($this->originalHttpTenantId !== null) {
            $_SERVER['HTTP_X_TENANT_ID'] = $this->originalHttpTenantId;
        } else {
            unset($_SERVER['HTTP_X_TENANT_ID']);
        }
    }

    public function testTransformDataPathWithTenantIdInArgv(): void
    {
        $context = [
            'DATA_PATH' => './var/tmp/data/app',
            'argv' => [
                'bin/console',
                'tenant:database:create',
                '--tenant_id',
                't1234'
            ]
        ];

        $transformer = new ContextTransformer($context);
        $transformer->transformDataPath();

        $this->assertEnvValue('DATA_PATH', './var/tmp/data/app/tenants/t1234');
    }

    private function assertEnvValue(string $envName, string $expectedValue): void
    {
        $this->assertTrue(isset($_ENV[$envName]));
        $this->assertEquals($expectedValue, $_ENV[$envName]);
        $this->assertEquals($expectedValue, getenv($envName));
    }

    public function testTransformDataPathWithTenantIdInArgvWithEqualSign(): void
    {
        $context = [
            'DATA_PATH' => './var/tmp/data/app',
            'argv' => [
                'bin/console',
                'tenant:database:create',
                '--tenant_id=t1234'
            ]
        ];

        $transformer = new ContextTransformer($context);
        $transformer->transformDataPath();

        $this->assertEnvValue('DATA_PATH', './var/tmp/data/app/tenants/t1234');
    }

    public function testTransformDataPathWithoutTenantId(): void
    {
        $context = [
            'DATA_PATH' => './var/tmp/data/app',
            'argv' => [
                'bin/console',
                'some:command'
            ]
        ];

        $transformer = new ContextTransformer($context);
        $transformer->transformDataPath();

        // DATA_PATH should remain unchanged if no tenant_id is provided
        $this->assertEquals('./var/tmp/data/app', $context['DATA_PATH']);
    }

    public function testTransformDatabaseUrlWithTenantIdInArgv(): void
    {
        $context = [
            'DATA_PATH' => './var/tmp/data/app',
            'DATABASE_URL' => 'sqlite:///var/tmp/data/app/sqlite/data.sqlite',
            'argv' => [
                'bin/console',
                'tenant:database:create',
                '--tenant_id',
                't1234'
            ]
        ];

        $transformer = new ContextTransformer($context);
        $transformer->transformDatabaseUrl();

        $this->assertEnvValue(
            'DATABASE_URL',
            'sqlite:///var/tmp/data/app/tenants/t1234/sqlite/data.sqlite',
        );
    }

    public function testTransformDatabaseUrlWithoutTenantId(): void
    {
        $context = [
            'DATABASE_URL' => 'sqlite:///var/tmp/data/app/sqlite/data.sqlite',
            'argv' => [
                'bin/console',
                'some:command'
            ]
        ];

        $transformer = new ContextTransformer($context);
        $transformer->transformDatabaseUrl();

        // DATABASE_URL should remain unchanged if no tenant_id is provided
        $this->assertEquals('sqlite:///var/tmp/data/app/sqlite/data.sqlite', $context['DATABASE_URL']);
    }

    public function testDatabaseUrlShouldRemainUnchangedWithoutDataPath(): void
    {
        $context = [
            'DATABASE_URL' => 'sqlite:///var/tmp/data/app/sqlite/data.sqlite',
            'argv' => [
                'bin/console',
                'tenant:database:create',
                '--tenant_id',
                't1234'
            ]
        ];

        $transformer = new ContextTransformer($context);
        $transformer->transformDatabaseUrl();

        // DATABASE_URL should remain unchanged if no tenant_id is provided
        $this->assertEquals('sqlite:///var/tmp/data/app/sqlite/data.sqlite', $context['DATABASE_URL']);
    }

    public function testExtractTenantIdFromArgv(): void
    {
        $context = [
            'argv' => [
                'bin/console',
                'tenant:database:create',
                '--tenant_id',
                't1234'
            ]
        ];

        $transformer = new ContextTransformer($context);
        $reflection = new \ReflectionClass($transformer);
        $method = $reflection->getMethod('extractTenantIdFromArgv');
        $method->setAccessible(true);

        $tenantId = $method->invokeArgs($transformer, []);
        $this->assertEquals('t1234', $tenantId);
    }

    public function testExtractTenantIdFromArgvWithoutTenantId(): void
    {
        $context = [
            'argv' => [
                'bin/console',
                'some:command'
            ]
        ];

        $transformer = new ContextTransformer($context);
        $reflection = new \ReflectionClass($transformer);
        $method = $reflection->getMethod('extractTenantIdFromArgv');
        $method->setAccessible(true);

        $tenantId = $method->invokeArgs($transformer, []);
        $this->assertNull($tenantId);
    }

    public function testTransformEnv(): void
    {
        // Arrange
        $_ENV['DATABASE_URL'] = 'sqlite:///var/tmp/data/app/sqlite/data.sqlite';
        $_ENV['DATA_PATH'] = './var/tmp/data/app';

        $context = [
            'DATABASE_URL' => 'sqlite:///var/tmp/data/app/sqlite/data.sqlite',
            'DATA_PATH' => './var/tmp/data/app',
            'argv' => [
                'bin/console',
                'tenant:database:create',
                '--tenant_id',
                't1234'
            ]
        ];

        // Act
        $transformer = new ContextTransformer($context);
        $transformer->transformDataPath();
        $transformer->transformDatabaseUrl();

        // Assert
        $this->assertEnvValue(
            'DATABASE_URL',
            'sqlite:///var/tmp/data/app/tenants/t1234/sqlite/data.sqlite'
        );
        $this->assertEnvValue(
            'DATA_PATH',
            './var/tmp/data/app/tenants/t1234'
        );
    }

    public function testShouldAddEnvIfTheyAreNotSet(): void
    {
        // Arrange
        unset($_ENV['DATABASE_URL']);
        unset($_ENV['DATA_PATH']);

        $context = [
            'DATABASE_URL' => 'sqlite:///var/tmp/data/app/sqlite/data.sqlite',
            'DATA_PATH' => './var/tmp/data/app',
            'argv' => [
                'bin/console',
                'tenant:database:create',
                '--tenant_id',
                't1234'
            ]
        ];

        // Act
        $transformer = new ContextTransformer($context);
        $transformer->transformDataPath();
        $transformer->transformDatabaseUrl();

        // Assert
        $this->assertEnvValue(
            'DATABASE_URL',
            'sqlite:///var/tmp/data/app/tenants/t1234/sqlite/data.sqlite'
        );
        $this->assertEnvValue(
            'DATA_PATH',
            './var/tmp/data/app/tenants/t1234'
        );
    }

    public function testShouldTransformInHttpContext(): void
    {
        $context = [
            'DATABASE_URL' => 'sqlite:///var/tmp/data/app/sqlite/data.sqlite',
            'DATA_PATH' => './var/tmp/data/app',
        ];

        $_SERVER['HTTP_X_TENANT_ID'] = 't1234';

        $transformer = new ContextTransformer($context);
        $transformer->transformDataPath();
        $transformer->transformDatabaseUrl();

        $this->assertEquals('./var/tmp/data/app/tenants/t1234', $context['DATA_PATH']);
        $this->assertEquals(
            'sqlite:///var/tmp/data/app/tenants/t1234/sqlite/data.sqlite',
            $context['DATABASE_URL']
        );
    }
}
