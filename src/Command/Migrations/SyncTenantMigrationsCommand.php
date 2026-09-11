<?php

declare(strict_types=1);

namespace Phariscope\MultiTenant\Command\Migrations;

use Phariscope\MultiTenant\Command\TenantConsoleOptionResolver;
use Phariscope\MultiTenant\Command\TenantConsoleProcessRunnerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class SyncTenantMigrationsCommand extends Command
{
    public function __construct(
        private readonly TenantConsoleProcessRunnerInterface $processRunner,
        private readonly TenantMigrationDatabaseInspector $databaseInspector,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('tenant:migrations:sync')
            ->setDescription(
                'Marks all migration versions as executed without running them (after tenant:schema:create).'
            )
            ->addOption('tenant_id', null, InputOption::VALUE_OPTIONAL, 'The canonical tenant id', null)
            ->addOption(
                'tenant_shortname',
                null,
                InputOption::VALUE_OPTIONAL,
                'Tenant shortname (resolved via tenants/tenants.sqlite under DATA_PATH)',
                null
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Allow sync when doctrine_migration_versions already contains rows'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $tenantId = TenantConsoleOptionResolver::resolveTenantId($input);
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }

        if (!$this->databaseInspector->tenantDatabaseExists($tenantId)) {
            $output->writeln(
                sprintf(
                    '<error>Tenant database file not found for "%s". Run tenant:schema:create first.</error>',
                    $tenantId
                )
            );

            return Command::FAILURE;
        }

        $executedCount = $this->databaseInspector->countExecutedMigrations($tenantId);
        $force = $input->getOption('force') === true;

        if ($executedCount > 0 && !$force) {
            $output->writeln(
                sprintf(
                    '<error>Migration history for tenant "%s" is not empty (%d version(s)). '
                    . 'Use --force only for exceptional recovery.</error>',
                    $tenantId,
                    $executedCount
                )
            );

            return Command::FAILURE;
        }

        $result = $this->processRunner->run('doctrine:migrations:version', [
            '--add' => true,
            '--all' => true,
            '--no-interaction' => true,
            '--tenant_id' => $tenantId,
        ]);

        if ($result->output !== '') {
            $output->write($result->output);
        }

        if ($result->exitCode !== 0) {
            $output->writeln(
                sprintf('<error>Migration sync failed for tenant "%s".</error>', $tenantId)
            );

            return Command::FAILURE;
        }

        $output->writeln('<info>Migration history synced for tenant "' . $tenantId . '".</info>');

        return Command::SUCCESS;
    }
}
