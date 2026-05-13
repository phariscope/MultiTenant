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

    public function testReturnsTenantIdWhenProvided(): void
    {
        // Arrange
        $input = new ArrayInput(['--tenant_id' => 't1'], $this->definition());

        // Act
        $resolved = TenantConsoleOptionResolver::resolveTenantId($input);

        // Assert
        $this->assertSame('t1', $resolved);
    }

    public function testRejectsBothOptions(): void
    {
        // Arrange
        $input = new ArrayInput([
            '--tenant_id' => 't1',
            '--tenant_shortname' => 'acme',
        ], $this->definition());
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Provide either --tenant_id or --tenant_shortname, not both.');

        // Act
        TenantConsoleOptionResolver::resolveTenantId($input);

        // Assert - PHPUnit verifies the exception
    }

    public function testRejectsNeitherOption(): void
    {
        // Arrange
        $input = new ArrayInput([], $this->definition());
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Provide --tenant_id or --tenant_shortname.');

        // Act
        TenantConsoleOptionResolver::resolveTenantId($input);

        // Assert - PHPUnit verifies the exception
    }

    public function testResolvesTenantShortnameUsingDataPath(): void
    {
        // Arrange
        $hadKey = array_key_exists('DATA_PATH', $_ENV);
        $previous = $hadKey ? $_ENV['DATA_PATH'] : null;
        $tmp = sys_get_temp_dir() . '/mt-tcor-' . uniqid('', true);

        try {
            $_ENV['DATA_PATH'] = $tmp;
            putenv('DATA_PATH=' . $tmp);

            $registry = TenantShortnameRegistry::fromApplicationDataPath($tmp);
            $registry->register('real-tenant-id', 'acme');

            $input = new ArrayInput(['--tenant_shortname' => 'acme'], $this->definition());

            // Act
            $resolved = TenantConsoleOptionResolver::resolveTenantId($input);

            // Assert
            $this->assertSame('real-tenant-id', $resolved);
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

    public function testThrowsWhenDataPathMissingForShortnameResolution(): void
    {
        // Arrange
        $hadKey = array_key_exists('DATA_PATH', $_ENV);
        $previous = $hadKey ? $_ENV['DATA_PATH'] : null;
        unset($_ENV['DATA_PATH']);
        putenv('DATA_PATH');

        $input = new ArrayInput(['--tenant_shortname' => 'any'], $this->definition());
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DATA_PATH must be set to resolve --tenant_shortname.');

        try {
            // Act
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

        // Assert - PHPUnit verifies the exception
    }

    public function testThrowsWhenShortnameUnknown(): void
    {
        // Arrange
        $hadKey = array_key_exists('DATA_PATH', $_ENV);
        $previous = $hadKey ? $_ENV['DATA_PATH'] : null;
        $tmp = sys_get_temp_dir() . '/mt-tcor-unknown-' . uniqid('', true);

        try {
            $_ENV['DATA_PATH'] = $tmp;
            putenv('DATA_PATH=' . $tmp);

            $input = new ArrayInput(['--tenant_shortname' => 'missing-slug'], $this->definition());
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('Unknown tenant_shortname "missing-slug".');

            // Act
            TenantConsoleOptionResolver::resolveTenantId($input);
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

        // Assert - PHPUnit verifies the exception
    }
}
