<?php

declare(strict_types=1);

namespace Phariscope\MultiTenant\Command;

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
            $this->parametersToArgv($parameters),
        );
    }

    /**
     * @param array<int|string, mixed> $parameters
     */
    public function run(string $commandName, array $parameters = []): TenantConsoleProcessResult
    {
        $process = new Process($this->buildCommand($commandName, $parameters), $this->projectDir);
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
    private function parametersToArgv(array $parameters): array
    {
        $argv = [];

        foreach ($parameters as $key => $value) {
            if (is_int($key)) {
                if (is_scalar($value)) {
                    $argv[] = (string) $value;
                }

                continue;
            }

            $optionName = ltrim($key, '-');

            if ($value === true) {
                $argv[] = '--' . $optionName;

                continue;
            }

            if ($value === false || $value === null) {
                continue;
            }

            $argv[] = '--' . $optionName;
            $argv[] = (string) $value;
        }

        return $argv;
    }
}
