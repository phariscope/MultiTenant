<?php

declare(strict_types=1);

namespace Phariscope\MultiTenant\Command;

use Phariscope\MultiTenant\DataFolder;
use Symfony\Component\Process\Process;

final class TenantConsoleProcessRunner implements TenantConsoleProcessRunnerInterface
{
    public function __construct(
        private readonly string $projectDir,
        private readonly string $phpBinary = \PHP_BINARY,
    ) {
    }

    /**
     * @param array<int|string, mixed> $parameters
     *
     * @return list<string>
     */
    public function buildCommand(string $commandName, array $parameters = []): array
    {
        return array_merge(
            [
                $this->phpBinary,
                $this->projectDir . '/bin/console',
                $commandName,
            ],
            $this->parametersToArgv($commandName, $parameters),
        );
    }

    /**
     * @param array<int|string, mixed> $parameters
     */
    public function run(string $commandName, array $parameters = []): TenantConsoleProcessResult
    {
        $process = new Process(
            $this->buildCommand($commandName, $parameters),
            $this->projectDir,
            $this->subprocessEnvironment($commandName, $parameters),
        );
        $process->setTimeout(null);
        $process->run();

        return new TenantConsoleProcessResult(
            $process->getExitCode() ?? 1,
            $process->getOutput() . $process->getErrorOutput(),
        );
    }

    /**
     * @param array<int|string, mixed> $parameters
     *
     * @return list<string>
     */
    private function parametersToArgv(string $commandName, array $parameters): array
    {
        $argv = [];
        $omitTenantCliOptions = str_starts_with($commandName, 'doctrine:');

        foreach ($parameters as $key => $value) {
            if (is_int($key)) {
                if (is_scalar($value)) {
                    $argv[] = (string) $value;
                }

                continue;
            }

            $optionName = ltrim($key, '-');

            if (
                $omitTenantCliOptions
                && ($optionName === 'tenant_id' || $optionName === 'tenant_shortname')
            ) {
                continue;
            }

            if ($value === true) {
                $argv[] = '--' . $optionName;

                continue;
            }

            if ($value === false || $value === null || !is_scalar($value)) {
                continue;
            }

            $argv[] = '--' . $optionName;
            $argv[] = (string) $value;
        }

        return $argv;
    }

    /**
     * Nested `bin/console` must start from the application DATA_PATH / DATABASE_URL,
     * not values already rewritten by the parent ContextTransformer.
     * Doctrine commands have no --tenant_id option: pass the tenant via HTTP_X_TENANT_ID
     * (already read by TenantManager / ContextTransformer).
     *
     * @param array<int|string, mixed> $parameters
     *
     * @return array<string, string>
     */
    private function subprocessEnvironment(string $commandName, array $parameters): array
    {
        $env = $this->unscopedTenantEnvironment();

        if (!str_starts_with($commandName, 'doctrine:')) {
            return $env;
        }

        $tenantId = $this->scalarParameter($parameters, 'tenant_id');
        if ($tenantId !== null) {
            $env['HTTP_X_TENANT_ID'] = $tenantId;
        }

        $tenantShortname = $this->scalarParameter($parameters, 'tenant_shortname');
        if ($tenantShortname !== null) {
            $env['HTTP_X_TENANT_SHORTNAME'] = $tenantShortname;
        }

        return $env;
    }

    /**
     * @param array<int|string, mixed> $parameters
     */
    private function scalarParameter(array $parameters, string $name): ?string
    {
        foreach ([$name, '--' . $name] as $key) {
            if (!isset($parameters[$key]) || !is_scalar($parameters[$key]) || $parameters[$key] === true) {
                continue;
            }

            $value = trim((string) $parameters[$key]);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function unscopedTenantEnvironment(): array
    {
        $dataFolder = new DataFolder();
        $applicationRoot = $dataFolder->getApplicationDataRoot();
        $currentDataPath = $dataFolder->getDataRootFolder();

        $env = [];
        if ($applicationRoot !== '') {
            $env['DATA_PATH'] = $applicationRoot;
        }

        $databaseUrl = $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL');
        if (!is_string($databaseUrl) || $databaseUrl === '') {
            return $env;
        }

        $env['DATABASE_URL'] = $this->unscopedSqliteDatabaseUrl(
            $databaseUrl,
            $currentDataPath,
            $applicationRoot,
        );

        return $env;
    }

    private function unscopedSqliteDatabaseUrl(
        string $databaseUrl,
        string $currentDataPath,
        string $applicationRoot,
    ): string {
        if (
            $applicationRoot === ''
            || $currentDataPath === ''
            || $currentDataPath === $applicationRoot
            || !str_contains($databaseUrl, $currentDataPath)
        ) {
            return $databaseUrl;
        }

        return str_replace($currentDataPath, $applicationRoot, $databaseUrl);
    }
}
