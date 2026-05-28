<?php

namespace Phariscope\MultiTenant\Tests;

use Phariscope\MultiTenant\ContextTransformer;
use Phariscope\MultiTenant\Infrastructure\TenantShortname\TenantShortnameRegistry;
use Phariscope\MultiTenant\Share\TenantException;
use Phariscope\MultiTenant\Tests\Share\EnsuresTenantDirectoryTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class ContextTransformerTest extends TestCase
{
    use EnsuresTenantDirectoryTrait;

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
        // Arrange
        $dataPath = './var/tmp/data/app';
        $this->ensureTenantDirectory($dataPath, 't1234');
        $context = [
            'DATA_PATH' => $dataPath,
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

        // Assert
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
        // Arrange
        $dataPath = './var/tmp/data/app';
        $this->ensureTenantDirectory($dataPath, 't1234');
        $context = [
            'DATA_PATH' => $dataPath,
            'argv' => [
                'bin/console',
                'tenant:database:create',
                '--tenant_id=t1234'
            ]
        ];

        // Act
        $transformer = new ContextTransformer($context);
        $transformer->transformDataPath();

        // Assert
        $this->assertEnvValue('DATA_PATH', './var/tmp/data/app/tenants/t1234');
    }

    public function testTransformDataPathWithoutTenantId(): void
    {
        // Arrange
        $context = [
            'DATA_PATH' => './var/tmp/data/app',
            'argv' => [
                'bin/console',
                'some:command'
            ]
        ];

        // Act
        $transformer = new ContextTransformer($context);
        $transformer->transformDataPath();

        // Assert
        $this->assertEquals('./var/tmp/data/app', $context['DATA_PATH']);
    }

    public function testTransformDatabaseUrlWithTenantIdInArgv(): void
    {
        // Arrange
        $dataPath = './var/tmp/data/app';
        $this->ensureTenantDirectory($dataPath, 't1234');
        $context = [
            'DATA_PATH' => $dataPath,
            'DATABASE_URL' => 'sqlite:///var/tmp/data/app/sqlite/data.sqlite',
            'argv' => [
                'bin/console',
                'tenant:database:create',
                '--tenant_id',
                't1234'
            ]
        ];

        // Act
        $transformer = new ContextTransformer($context);
        $transformer->transformDatabaseUrl();

        // Assert
        $this->assertEnvValue(
            'DATABASE_URL',
            'sqlite:///var/tmp/data/app/tenants/t1234/sqlite/data.sqlite',
        );
    }

    public function testTransformDatabaseUrlWithoutTenantId(): void
    {
        // Arrange
        $context = [
            'DATABASE_URL' => 'sqlite:///var/tmp/data/app/sqlite/data.sqlite',
            'argv' => [
                'bin/console',
                'some:command'
            ]
        ];

        // Act
        $transformer = new ContextTransformer($context);
        $transformer->transformDatabaseUrl();

        // Assert
        $this->assertEquals('sqlite:///var/tmp/data/app/sqlite/data.sqlite', $context['DATABASE_URL']);
    }

    public function testDatabaseUrlThrowsWhenTenantIdInArgvWithoutDataPath(): void
    {
        // Arrange
        $context = [
            'DATABASE_URL' => 'sqlite:///var/tmp/data/app/sqlite/data.sqlite',
            'argv' => [
                'bin/console',
                'tenant:database:create',
                '--tenant_id',
                't1234'
            ]
        ];

        // Assert
        $this->expectException(TenantException::class);
        $this->expectExceptionMessage('Tenant not found: t1234');
        (new ContextTransformer($context))->transformDatabaseUrl();
    }

    public function testExtractTenantIdFromArgv(): void
    {
        // Arrange
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

        // Act
        $tenantId = $method->invokeArgs($transformer, []);

        // Assert
        $this->assertEquals('t1234', $tenantId);
    }

    public function testExtractTenantIdFromArgvWithoutTenantId(): void
    {
        // Arrange
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

        // Act
        $tenantId = $method->invokeArgs($transformer, []);

        // Assert
        $this->assertNull($tenantId);
    }

    public function testTransformEnv(): void
    {
        // Arrange
        $dataPath = './var/tmp/data/app';
        $this->ensureTenantDirectory($dataPath, 't1234');
        $_ENV['DATABASE_URL'] = 'sqlite:///var/tmp/data/app/sqlite/data.sqlite';
        $_ENV['DATA_PATH'] = $dataPath;

        $context = [
            'DATABASE_URL' => 'sqlite:///var/tmp/data/app/sqlite/data.sqlite',
            'DATA_PATH' => $dataPath,
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
        $dataPath = './var/tmp/data/app';
        $this->ensureTenantDirectory($dataPath, 't1234');
        unset($_ENV['DATABASE_URL']);
        unset($_ENV['DATA_PATH']);

        $context = [
            'DATABASE_URL' => 'sqlite:///var/tmp/data/app/sqlite/data.sqlite',
            'DATA_PATH' => $dataPath,
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
        // Arrange
        $dataPath = sys_get_temp_dir() . '/mt-ctx-http-' . uniqid('', true);
        mkdir($dataPath, 0775, true);
        $this->ensureTenantDirectory($dataPath, 't1234');
        $_ENV['DATA_PATH'] = $dataPath;
        putenv('DATA_PATH=' . $dataPath);
        $context = [
            'DATABASE_URL' => 'sqlite:///' . $dataPath . '/sqlite/data.sqlite',
            'DATA_PATH' => $dataPath,
        ];

        $_SERVER['HTTP_X_TENANT_ID'] = 't1234';

        // Act
        $transformer = new ContextTransformer($context);
        $transformer->transformDataPath();
        $transformer->transformDatabaseUrl();

        // Assert
        $this->assertEquals($dataPath . '/tenants/t1234', $context['DATA_PATH']);
        $this->assertEquals(
            'sqlite:///' . $dataPath . '/tenants/t1234/sqlite/data.sqlite',
            $context['DATABASE_URL']
        );

        (new Filesystem())->remove($dataPath);
    }

    public function testTryCreateFromEnvAfterTransformDataPathUsesGlobalShortnameRegistry(): void
    {
        $hadKey = array_key_exists('DATA_PATH', $_ENV);
        $previous = $hadKey ? $_ENV['DATA_PATH'] : null;
        $appRoot = sys_get_temp_dir() . '/mt-ctx-global-' . uniqid('', true);

        try {
            $this->ensureTenantDirectory($appRoot, 'campus26');
            $context = [
                'DATA_PATH' => $appRoot,
                'argv' => [
                    'bin/console',
                    'tenant:database:create',
                    '--tenant_id',
                    'campus26',
                    '--tenant_shortname',
                    'c26',
                ],
            ];

            $transformer = new ContextTransformer($context);
            $transformer->transformDataPath();

            $this->assertEnvValue('DATA_PATH', $appRoot . '/tenants/campus26');

            $registry = TenantShortnameRegistry::tryCreateFromEnv();
            $this->assertNotNull($registry);
            $registry->register('campus26', 'c26');

            $globalRegistry = TenantShortnameRegistry::fromApplicationDataPath($appRoot);
            $this->assertSame('campus26', $globalRegistry->resolveTenantId('c26'));
            $this->assertFileDoesNotExist($appRoot . '/tenants/campus26/tenants/tenants.sqlite');
        } finally {
            (new Filesystem())->remove($appRoot);
            if ($hadKey && is_string($previous)) {
                $_ENV['DATA_PATH'] = $previous;
                putenv('DATA_PATH=' . $previous);
            } else {
                unset($_ENV['DATA_PATH']);
                putenv('DATA_PATH');
            }
        }
    }

    public function testTransformDataPathWithTenantShortnameInArgv(): void
    {
        // Arrange
        $hadKey = array_key_exists('DATA_PATH', $_ENV);
        $previous = $hadKey ? $_ENV['DATA_PATH'] : null;
        $base = sys_get_temp_dir() . '/mt-ctxsn-' . uniqid('', true);

        try {
            $registry = TenantShortnameRegistry::fromApplicationDataPath($base);
            $registry->register('tid-slug', 'acme');
            $this->ensureTenantDirectory($base, 'tid-slug');

            $context = [
                'DATA_PATH' => $base,
                'argv' => [
                    'bin/console',
                    'tenant:database:create',
                    '--tenant_shortname',
                    'acme',
                ],
            ];

            $expected = $base . '/tenants/tid-slug';

            // Act
            $transformer = new ContextTransformer($context);
            $transformer->transformDataPath();

            // Assert
            $this->assertEnvValue('DATA_PATH', $expected);
        } finally {
            (new Filesystem())->remove($base);
            if ($hadKey && is_string($previous)) {
                $_ENV['DATA_PATH'] = $previous;
                putenv('DATA_PATH=' . $previous);
            } else {
                unset($_ENV['DATA_PATH']);
                putenv('DATA_PATH');
            }
        }
    }

    public function testTransformDatabaseUrlLeavesNonSqliteUrlUnchanged(): void
    {
        // Arrange
        $dataPath = './var/tmp/data/app';
        $this->ensureTenantDirectory($dataPath, 't1');
        $context = [
            'DATA_PATH' => $dataPath,
            'DATABASE_URL' => 'mysql://user:pass@127.0.0.1:3306/appdb',
            'argv' => [
                'bin/console',
                'tenant:database:create',
                '--tenant_id',
                't1',
            ],
        ];

        // Act
        $transformer = new ContextTransformer($context);
        $transformer->transformDatabaseUrl();

        // Assert
        $this->assertSame('mysql://user:pass@127.0.0.1:3306/appdb', $context['DATABASE_URL']);
    }

    public function testExtractTenantShortnameFromArgvSupportsEqualsSyntax(): void
    {
        // Arrange
        $context = [
            'argv' => [
                'bin/console',
                'some:command',
                '--tenant_shortname=brand-one',
            ],
        ];
        $transformer = new ContextTransformer($context);
        $reflection = new \ReflectionClass($transformer);
        $method = $reflection->getMethod('extractTenantShortnameFromArgv');
        $method->setAccessible(true);

        // Act
        $shortname = $method->invoke($transformer);

        // Assert
        $this->assertSame('brand-one', $shortname);
    }

    public function testExtractTenantIdFromArgvSpaceSeparatedForm(): void
    {
        // Arrange
        $context = [
            'argv' => [
                'bin/console',
                'tenant:database:create',
                '--tenant_id',
                'only-space-form',
            ],
        ];
        $transformer = new ContextTransformer($context);
        $method = (new \ReflectionClass($transformer))->getMethod('extractTenantIdFromArgv');
        $method->setAccessible(true);

        // Act
        $tenantId = $method->invoke($transformer);

        // Assert
        $this->assertSame('only-space-form', $tenantId);
    }

    public function testTransformDataPathThrowsForUnknownTenantIdInArgv(): void
    {
        $base = sys_get_temp_dir() . '/mt-ctx-bad-id-' . uniqid('', true);
        mkdir($base, 0775, true);

        try {
            $context = [
                'DATA_PATH' => $base,
                'argv' => [
                    'bin/console',
                    'tenant:database:create',
                    '--tenant_id',
                    'missing-tenant',
                ],
            ];

            $this->expectException(TenantException::class);
            $this->expectExceptionMessage('Tenant not found: missing-tenant');
            (new ContextTransformer($context))->transformDataPath();
        } finally {
            (new Filesystem())->remove($base);
        }
    }

    public function testTransformDataPathThrowsForUnknownTenantShortnameInArgv(): void
    {
        $base = sys_get_temp_dir() . '/mt-ctx-bad-sn-' . uniqid('', true);
        mkdir($base, 0775, true);

        try {
            TenantShortnameRegistry::fromApplicationDataPath($base);
            $context = [
                'DATA_PATH' => $base,
                'argv' => [
                    'bin/console',
                    'tenant:database:create',
                    '--tenant_shortname',
                    'bad-slug',
                ],
            ];

            $this->expectException(TenantException::class);
            $this->expectExceptionMessage('Unknown tenant: bad-slug');
            (new ContextTransformer($context))->transformDataPath();
        } finally {
            (new Filesystem())->remove($base);
        }
    }

    public function testTransformDataPathThrowsForTenantShortnameEqualsSyntaxWhenUnknown(): void
    {
        $base = sys_get_temp_dir() . '/mt-ctx-bad-sn-eq-' . uniqid('', true);
        mkdir($base, 0775, true);

        try {
            TenantShortnameRegistry::fromApplicationDataPath($base);
            $context = [
                'DATA_PATH' => $base,
                'argv' => [
                    'bin/console',
                    'some:command',
                    '--tenant_shortname=bad-slug',
                ],
            ];

            $this->expectException(TenantException::class);
            $this->expectExceptionMessage('Unknown tenant: bad-slug');
            (new ContextTransformer($context))->transformDataPath();
        } finally {
            (new Filesystem())->remove($base);
        }
    }
}
