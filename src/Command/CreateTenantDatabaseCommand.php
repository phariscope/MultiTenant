<?php

namespace Phariscope\MultiTenant\Command;

use Doctrine\ORM\EntityManagerInterface;
use Phariscope\MultiTenant\Doctrine\DatabaseTools;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CreateTenantDatabaseCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this
            ->setName('tenant:database:create')
            ->setDescription('Creates a new database for a tenant.')
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

        try {
            $databaseTools = new DatabaseTools();
            $databaseTools->createDatabase($this->entityManager);

            $output->writeln('<info>Database for tenant "' . $tenantId . '" created successfully.</info>');
        } catch (\Exception $e) {
            $output->writeln(
                sprintf(
                    '<error>Could not create database for tenant "%s": %s</error>',
                    $tenantId,
                    $e->getMessage()
                )
            );
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
