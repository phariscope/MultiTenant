<?php

declare(strict_types=1);

namespace Phariscope\MultiTenant\Tests\Command;

use Phariscope\MultiTenant\Command\TenantConsoleProcessRunner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class TenantConsoleProcessRunnerTest extends TestCase
{
    /** @var array{hadEnv: bool, env: mixed, getenv: string|false} */
    private array $savedDataPath;

    /** @var array{hadEnv: bool, env: mixed, getenv: string|false} */
    private array $savedDatabaseUrl;

    private ?string $fakeProjectDir = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->savedDataPath = $this->captureEnv('DATA_PATH');
        $this->savedDatabaseUrl = $this->captureEnv('DATABASE_URL');
    }

    protected function tearDown(): void
    {
        $this->restoreEnv('DATA_PATH', $this->savedDataPath);
        $this->restoreEnv('DATABASE_URL', $this->savedDatabaseUrl);
        putenv('MT_RUNNER_SENTINEL');
        unset($_ENV['MT_RUNNER_SENTINEL']);

        if ($this->fakeProjectDir !== null && is_dir($this->fakeProjectDir)) {
            (new Filesystem())->remove($this->fakeProjectDir);
            $this->fakeProjectDir = null;
        }

        parent::tearDown();
    }

    public function testBuildCommandPrefixesBareOptionNames(): void
    {
        // Arrange
        $runner = new TenantConsoleProcessRunner('/app', 'php');

        // Act
        $command = $runner->buildCommand('doctrine:migrations:migrate', [
            'no-interaction' => true,
            'tenant_id' => 'tenant-abc',
        ]);

        // Assert
        $this->assertSame(
            [
                'php',
                '/app/bin/console',
                'doctrine:migrations:migrate',
                '--no-interaction',
                '--tenant_id',
                'tenant-abc',
            ],
            $command
        );
    }

    public function testBuildCommandDoesNotDoubleDashOptionKeys(): void
    {
        // Arrange
        $runner = new TenantConsoleProcessRunner('/app', 'php');

        // Act
        $command = $runner->buildCommand('tenant:migrations:migrate', [
            '--tenant_id' => 'cl_demo10_yve5d6q',
        ]);

        // Assert
        $this->assertSame(
            [
                'php',
                '/app/bin/console',
                'tenant:migrations:migrate',
                '--tenant_id',
                'cl_demo10_yve5d6q',
            ],
            $command
        );
        $this->assertNotContains('----tenant_id=cl_demo10_yve5d6q', $command);
    }

    public function testRunPassesApplicationDataRootWhenParentDataPathIsTenantScopedAndDatabaseUrlIsRoot(): void
    {
        // Arrange — parent already scoped DATA_PATH; DATABASE_URL still the app root (Dotenv reload)
        $appRoot = '/var/captain-learning/data';
        $this->setEnv('DATA_PATH', $appRoot . '/tenants/cl_demo10_5eqi54i');
        $rootDatabaseUrl = 'sqlite:////var/captain-learning/data/database/captain-learning.sqlite';
        $this->setEnv('DATABASE_URL', $rootDatabaseUrl);
        $runner = new TenantConsoleProcessRunner($this->createFakeConsoleProject(), \PHP_BINARY);

        // Act
        $result = $runner->run('doctrine:migrations:migrate', [
            'tenant_id' => 'cl_demo10_5eqi54i',
        ]);

        // Assert
        $this->assertSame(0, $result->exitCode, $result->output);
        $this->assertSame(
            [$appRoot, $rootDatabaseUrl],
            $this->envDumpLines($result->output)
        );
    }

    public function testRunUnscopesTenantSqliteUrlWhenParentAlreadyRewroteDatabaseUrl(): void
    {
        // Arrange — parent transformed both DATA_PATH and DATABASE_URL
        $appRoot = '/tmp/app';
        $tenantDataPath = $appRoot . '/tenants/t1';
        $this->setEnv('DATA_PATH', $tenantDataPath);
        $this->setEnv('DATABASE_URL', 'sqlite:///' . $tenantDataPath . '/database/app.sqlite');
        $runner = new TenantConsoleProcessRunner($this->createFakeConsoleProject(), \PHP_BINARY);

        // Act
        $result = $runner->run('doctrine:migrations:migrate', [
            'tenant_id' => 't1',
        ]);

        // Assert
        $this->assertSame(0, $result->exitCode, $result->output);
        $this->assertSame(
            [$appRoot, 'sqlite:///' . $appRoot . '/database/app.sqlite'],
            $this->envDumpLines($result->output)
        );
    }

    public function testRunKeepsUnrelatedEnvironmentVariables(): void
    {
        // Arrange
        $this->setEnv('DATA_PATH', '/tmp/app/tenants/t1');
        $this->setEnv('DATABASE_URL', 'sqlite:////tmp/app/database/app.sqlite');
        $this->setEnv('MT_RUNNER_SENTINEL', 'keep-me');
        $this->fakeProjectDir = sys_get_temp_dir() . '/mt-console-runner-' . uniqid('', true);
        mkdir($this->fakeProjectDir . '/bin', 0775, true);
        file_put_contents(
            $this->fakeProjectDir . '/bin/console',
            <<<'PHP'
<?php
fwrite(STDOUT, getenv('MT_RUNNER_SENTINEL') ?: '');
exit(0);
PHP
        );
        $runner = new TenantConsoleProcessRunner($this->fakeProjectDir, \PHP_BINARY);

        // Act
        $result = $runner->run('doctrine:migrations:status');

        // Assert
        $this->assertSame(0, $result->exitCode, $result->output);
        $this->assertSame('keep-me', trim($result->output));
    }

    private function createFakeConsoleProject(): string
    {
        $this->fakeProjectDir = sys_get_temp_dir() . '/mt-console-runner-' . uniqid('', true);
        mkdir($this->fakeProjectDir . '/bin', 0775, true);
        file_put_contents(
            $this->fakeProjectDir . '/bin/console',
            <<<'PHP'
<?php
fwrite(STDOUT, getenv('DATA_PATH') . "\n");
fwrite(STDOUT, getenv('DATABASE_URL') . "\n");
exit(0);
PHP
        );

        return $this->fakeProjectDir;
    }

    /**
     * @return list<string>
     */
    private function envDumpLines(string $output): array
    {
        return explode("\n", trim($output));
    }

    /**
     * @return array{hadEnv: bool, env: mixed, getenv: string|false}
     */
    private function captureEnv(string $name): array
    {
        $getenvValue = getenv($name);

        return [
            'hadEnv' => array_key_exists($name, $_ENV),
            'env' => $_ENV[$name] ?? null,
            'getenv' => is_string($getenvValue) && $getenvValue !== '' ? $getenvValue : false,
        ];
    }

    private function setEnv(string $name, string $value): void
    {
        $_ENV[$name] = $value;
        putenv($name . '=' . $value);
    }

    /**
     * @param array{hadEnv: bool, env: mixed, getenv: string|false} $state
     */
    private function restoreEnv(string $name, array $state): void
    {
        if ($state['hadEnv'] && is_string($state['env'])) {
            $_ENV[$name] = $state['env'];
        } else {
            unset($_ENV[$name]);
        }

        if (is_string($state['getenv'])) {
            putenv($name . '=' . $state['getenv']);
        } else {
            putenv($name);
        }
    }
}
