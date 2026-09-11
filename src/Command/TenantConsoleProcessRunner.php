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
     */
    public function run(string $commandName, array $parameters = []): TenantConsoleProcessResult
    {
        $command = array_merge(
            [
                $this->phpBinary,
                $this->projectDir . '/bin/console',
                $commandName,
            ],
            self::parametersToArgv($parameters),
        );

        $process = new Process($command, $this->projectDir);
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
    private static function parametersToArgv(array $parameters): array
    {
        $argv = [];

        foreach ($parameters as $key => $value) {
            if (is_int($key)) {
                if (is_scalar($value)) {
                    $argv[] = (string) $value;
                }

                continue;
            }

            if ($value === true) {
                $argv[] = '--' . $key;

                continue;
            }

            if ($value === false || $value === null) {
                continue;
            }

            $argv[] = '--' . $key . '=' . (string) $value;
        }

        return $argv;
    }
}
