<?php

namespace Phariscope\MultiTenant\Command;

use Doctrine\ORM\EntityManagerInterface;
use Phariscope\MultiTenant\Doctrine\MultiTenantEntityManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Exception\RuntimeException;

use function SafePHP\strval;

#[AsCommand(name: 'tenant:database:create')]
class CreateTenantDatabaseCommand extends Command
{
    private MultiTenantEntityManager $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = new MultiTenantEntityManager($entityManager);
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('tenant:database:create') // Nom explicite de la commande
            ->setDescription('Creates a new database for a tenant.')
            ->addArgument('tenant_id', InputArgument::REQUIRED, 'The ID of the tenant');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tenantId = strval($input->getArgument('tenant_id'));

        if (!$tenantId) {
            throw new RuntimeException('Tenant ID is required.');
        }

        try {
            $this->entityManager->createDatabase($tenantId);
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
