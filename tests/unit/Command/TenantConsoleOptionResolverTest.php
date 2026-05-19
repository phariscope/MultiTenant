<?php

declare(strict_types=1);

namespace Phariscope\MultiTenant\Tests\Command;

use InvalidArgumentException;
use Phariscope\MultiTenant\Command\TenantConsoleOptionResolver;
use Phariscope\MultiTenant\Infrastructure\TenantShortname\TenantShortnameRegistry;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Filesystem\Filesystem;

class TenantConsoleOptionResolverTest extends TestCase
{
    private function definition(): InputDefinition
    {
        return new InputDefinition([
            new InputOption('tenant_id', null, InputOption::VALUE_OPTIONAL),
            new InputOption('tenant_shortname', null, InputOption::VALUE_OPTIONAL),
        ]);
    }

    public function testRegistersAndReturnsTenantIdWhenOnlyTenantIdProvided(): void
    {
        $hadKey = array_key_exists('DATA_PATH', $_ENV);
        $previous = $hadKey ? $_ENV['DATA_PATH'] : null;
        $tmp = sys_get_temp_dir() . '/mt-tcor-id-' . uniqid('', true);

        try {
            $_ENV['DATA_PATH'] = $tmp;
            putenv('DATA_PATH=' . $tmp);

            $input = new ArrayInput(['--tenant_id' => 't1'], $this->definition());

            $resolved = TenantConsoleOptionResolver::resolveTenantId($input);

            $this->assertSame('t1', $resolved);
            $registry = TenantShortnameRegistry::fromApplicationDataPath($tmp);
            $this->assertSame('t1', $registry->resolveTenantId('t1'));
        } finally {
            (new Filesystem())->remove($tmp);
            if ($hadKey && is_string($previous)) {
                $_ENV['DATA_PATH'] = $previous;
                putenv('DATA_PATH=' . $previous);
            } else {
                unset($_ENV['DATA_PATH']);
                putenv('DATA_PATH');
            }
        }
    }

    public function testRegistersMappingWhenBothOptionsProvided(): void
    {
        $hadKey = array_key_exists('DATA_PATH', $_ENV);
        $previous = $hadKey ? $_ENV['DATA_PATH'] : null;
        $tmp = sys_get_temp_dir() . '/mt-tcor-both-' . uniqid('', true);

        try {
            $_ENV['DATA_PATH'] = $tmp;
            putenv('DATA_PATH=' . $tmp);

            $input = new ArrayInput([
                '--tenant_id' => 'real-tenant-id',
                '--tenant_shortname' => 'acme',
            ], $this->definition());

            $resolved = TenantConsoleOptionResolver::resolveTenantId($input);

            $this->assertSame('real-tenant-id', $resolved);
            $registry = TenantShortnameRegistry::fromApplicationDataPath($tmp);
            $this->assertSame('real-tenant-id', $registry->resolveTenantId('acme'));
        } finally {
            (new Filesystem())->remove($tmp);
            if ($hadKey && is_string($previous)) {
                $_ENV['DATA_PATH'] = $previous;
                putenv('DATA_PATH=' . $previous);
            } else {
                unset($_ENV['DATA_PATH']);
                putenv('DATA_PATH');
            }
        }
    }

    public function testRejectsNeitherOption(): void
    {
        $input = new ArrayInput([], $this->definition());
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Provide --tenant_id.');

        TenantConsoleOptionResolver::resolveTenantId($input);
    }

    public function testRejectsShortnameWithoutTenantId(): void
    {
        $input = new ArrayInput(['--tenant_shortname' => 'acme'], $this->definition());
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('--tenant_shortname requires --tenant_id.');

        TenantConsoleOptionResolver::resolveTenantId($input);
    }

    public function testThrowsWhenDataPathMissing(): void
    {
        $hadKey = array_key_exists('DATA_PATH', $_ENV);
        $previous = $hadKey ? $_ENV['DATA_PATH'] : null;
        unset($_ENV['DATA_PATH']);
        putenv('DATA_PATH');

        $input = new ArrayInput(['--tenant_id' => 't1'], $this->definition());
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DATA_PATH must be set to register tenant shortname mapping');

        try {
            TenantConsoleOptionResolver::resolveTenantId($input);
        } finally {
            if ($hadKey && is_string($previous)) {
                $_ENV['DATA_PATH'] = $previous;
                putenv('DATA_PATH=' . $previous);
            } else {
                unset($_ENV['DATA_PATH']);
                putenv('DATA_PATH');
            }
        }
    }
}
