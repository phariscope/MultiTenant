<?php

declare(strict_types=1);

namespace Phariscope\MultiTenant\Command;

use InvalidArgumentException;
use Phariscope\MultiTenant\Infrastructure\TenantShortname\TenantShortnameRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ShowTenantShortnameCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('tenant:shortname:show')
            ->setDescription('Prints the tenant shortname registered for a canonical tenant id.')
            ->addOption('tenant_id', null, InputOption::VALUE_OPTIONAL, 'The canonical tenant id', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $tenantId = $this->resolveTenantIdOption($input);
        } catch (InvalidArgumentException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }

        $registry = TenantShortnameRegistry::tryCreateFromEnv();
        if ($registry === null) {
            $output->writeln(
                '<error>DATA_PATH must be set to read tenant shortname mapping from tenants/tenants.sqlite.</error>'
            );

            return Command::FAILURE;
        }

        $shortname = $registry->resolveShortname($tenantId);
        if ($shortname === null) {
            $output->writeln(
                '<error>No shortname registered for tenant "' . $tenantId . '".</error>'
            );

            return Command::FAILURE;
        }

        $output->writeln($shortname);

        return Command::SUCCESS;
    }

    private function resolveTenantIdOption(InputInterface $input): string
    {
        $id = $input->getOption('tenant_id');
        $tenantIdString = is_string($id) ? trim($id) : '';

        if ($tenantIdString === '') {
            throw new InvalidArgumentException('Provide --tenant_id.');
        }

        return $tenantIdString;
    }
}
