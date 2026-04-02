<?php

namespace Phariscope\MultiTenant\Command;

use Doctrine\ORM\EntityManagerInterface;
use Phariscope\MultiTenant\Doctrine\DatabaseTools;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function SafePHP\strval;

class UpdateTenantSchemaCommand extends Command
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
            ->setName('tenant:schema:update')
            ->setDescription('Updates the database schema for a tenant to match the current mapping.')
            ->addOption('tenant_id', null, InputOption::VALUE_REQUIRED, 'The ID of the tenant')
            ->addOption(
                'dump-sql',
                null,
                InputOption::VALUE_NONE,
                'Dumps the generated SQL statements to the screen (does not execute them).'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Executes the generated SQL statements against the database.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tenantId = strval($input->getOption('tenant_id'));
        $dumpSql = $input->getOption('dump-sql') === true;
        $force = $input->getOption('force') === true;

        try {
            $databaseTools = new DatabaseTools();
            $sqls = $databaseTools->getUpdateSchemaSql($this->entityManager);

            if ($sqls === []) {
                $output->writeln('<info>Schema for tenant "' . $tenantId . '" is up to date.</info>');

                return Command::SUCCESS;
            }

            if ($dumpSql) {
                $output->writeln(implode(";\n", $sqls) . ';');
            }

            if ($force) {
                $databaseTools->updateSchema($this->entityManager);
                $output->writeln('<info>Schema for tenant "' . $tenantId . '" updated successfully.</info>');
            }

            if ($dumpSql || $force) {
                return Command::SUCCESS;
            }

            $output->writeln(
                '<comment>Pending schema changes: use --force to apply or --dump-sql to show SQL.</comment>'
            );

            return Command::FAILURE;
        } catch (\Exception $e) {
            $output->writeln(
                sprintf(
                    '<error>Could not update schema for tenant "%s": %s</error>',
                    $tenantId,
                    $e->getMessage()
                )
            );

            return Command::FAILURE;
        }
    }
}
