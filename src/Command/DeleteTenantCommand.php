<?php

declare(strict_types=1);

namespace Phariscope\MultiTenant\Command;

use Phariscope\MultiTenant\Application\Service\Tenant\DeleteTenant\DeleteTenantService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class DeleteTenantCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('tenant:delete')
            ->setDescription(
                'Deletes a tenant data directory and removes its shortname mapping from tenants.sqlite.'
            )
            ->addOption('tenant_id', null, InputOption::VALUE_REQUIRED, 'The canonical tenant id');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getOption('tenant_id');
        $tenantId = is_string($id) ? trim($id) : '';
        if ($tenantId === '') {
            $output->writeln('<error>Provide --tenant_id.</error>');

            return Command::FAILURE;
        }

        try {
            (new DeleteTenantService())->execute($tenantId);
        } catch (Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }

        $output->writeln('<info>Tenant "' . $tenantId . '" deleted.</info>');

        return Command::SUCCESS;
    }
}
