<?php

namespace Phariscope\MultiTenant\Command;

use Doctrine\ORM\EntityManagerInterface;
use Phariscope\MultiTenant\Doctrine\DatabaseTools;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CreateTenantSchemaCommand extends Command
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
            ->setName('tenant:schema:create') // Nom explicite de la commande
            ->setDescription('Creates schema for a tenant.')
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
            $databaseTools->createSchema($this->entityManager);
            $output->writeln('<info>Schema for tenant "' . $tenantId . '" created successfully.</info>');
        } catch (\Exception $e) {
            $output->writeln(
                sprintf(
                    '<error>Could not create schema for tenant "%s": %s</error>',
                    $tenantId,
                    $e->getMessage()
                )
            );
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
