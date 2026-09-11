<?php

declare(strict_types=1);

namespace Phariscope\MultiTenant\Command\Migrations;

use Phariscope\MultiTenant\Command\TenantConsoleOptionResolver;
use Phariscope\MultiTenant\Command\TenantConsoleProcessRunnerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class StatusTenantMigrationsCommand extends Command
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
            ->setName('tenant:migrations:status')
            ->setDescription('Shows Doctrine migration status for a single tenant.')
            ->addOption('tenant_id', null, InputOption::VALUE_OPTIONAL, 'The canonical tenant id', null)
            ->addOption(
                'tenant_shortname',
                null,
                InputOption::VALUE_OPTIONAL,
                'Tenant shortname (resolved via tenants/tenants.sqlite under DATA_PATH)',
                null
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
                    '<error>Tenant database file not found for "%s".</error>',
                    $tenantId
                )
            );

            return Command::FAILURE;
        }

        $result = $this->processRunner->run('doctrine:migrations:status', [
            '--tenant_id' => $tenantId,
        ]);

        if ($result->output !== '') {
            $output->write($result->output);
        }

        return $result->exitCode === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
