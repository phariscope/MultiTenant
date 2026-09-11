<?php

declare(strict_types=1);

namespace Phariscope\MultiTenant\Command\Migrations;

use Phariscope\MultiTenant\Command\TenantConsoleProcessRunnerInterface;
use Phariscope\MultiTenant\Infrastructure\TenantShortname\TenantShortnameRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class MigrateAllTenantMigrationsCommand extends Command
{
    public function __construct(
        private readonly TenantConsoleProcessRunnerInterface $processRunner,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('tenant:migrations:migrate-all')
            ->setDescription('Runs pending Doctrine migrations for every registered tenant.')
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'List tenants and show migration status without applying migrations'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $registry = TenantShortnameRegistry::tryCreateFromEnv();
        if ($registry === null) {
            $output->writeln('<error>DATA_PATH must be set to resolve the tenant registry.</error>');

            return Command::FAILURE;
        }

        $tenantIds = $registry->listRegisteredTenantIds();
        if ($tenantIds === []) {
            $output->writeln('<comment>No registered tenants found.</comment>');

            return Command::SUCCESS;
        }

        $dryRun = $input->getOption('dry-run') === true;
        $failedTenantIds = [];

        foreach ($tenantIds as $tenantId) {
            $output->writeln(sprintf('<info>Processing tenant "%s"…</info>', $tenantId));

            $commandName = $dryRun ? 'tenant:migrations:status' : 'tenant:migrations:migrate';
            $result = $this->processRunner->run($commandName, [
                '--tenant_id' => $tenantId,
            ]);

            if ($result->output !== '') {
                $output->write($result->output);
            }

            if ($result->exitCode !== 0) {
                $failedTenantIds[] = $tenantId;
                $output->writeln(
                    sprintf('<error>Command failed for tenant "%s".</error>', $tenantId)
                );

                break;
            }
        }

        if ($failedTenantIds !== []) {
            $output->writeln(
                sprintf(
                    '<error>Stopped after failure on tenant(s): %s</error>',
                    implode(', ', $failedTenantIds)
                )
            );

            return Command::FAILURE;
        }

        $output->writeln(
            sprintf(
                '<info>Successfully processed %d tenant(s).</info>',
                count($tenantIds)
            )
        );

        return Command::SUCCESS;
    }
}
