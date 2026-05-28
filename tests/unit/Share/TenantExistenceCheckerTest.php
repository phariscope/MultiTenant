<?php

declare(strict_types=1);

namespace Phariscope\MultiTenant\Tests\Share;

use Phariscope\MultiTenant\Infrastructure\TenantShortname\TenantShortnameRegistry;
use Phariscope\MultiTenant\Share\TenantException;
use Phariscope\MultiTenant\Share\TenantExistenceChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class TenantExistenceCheckerTest extends TestCase
{
    use EnsuresTenantDirectoryTrait;
    use IsolatesDataPathEnvTrait;

    protected function setUp(): void
    {
        $this->setUpDataPathEnvSnapshot();
    }

    protected function tearDown(): void
    {
        $this->tearDownDataPathEnvSnapshot();
    }

    public function testReturnsNullWhenNoTenantProvided(): void
    {
        $base = sys_get_temp_dir() . '/mt-checker-none-' . uniqid('', true);
        mkdir($base, 0775, true);

        try {
            $checker = new TenantExistenceChecker($base);
            $this->assertNull($checker->assertResolvable(null, null));
        } finally {
            (new Filesystem())->remove($base);
        }
    }

    public function testValidTenantIdWithExistingFolder(): void
    {
        $base = sys_get_temp_dir() . '/mt-checker-id-' . uniqid('', true);
        mkdir($base, 0775, true);
        $this->ensureTenantDirectory($base, 'tid-ok');

        try {
            $checker = new TenantExistenceChecker($base);
            $this->assertSame('tid-ok', $checker->assertResolvable('tid-ok', null));
        } finally {
            (new Filesystem())->remove($base);
        }
    }

    public function testUnknownTenantIdThrows(): void
    {
        $base = sys_get_temp_dir() . '/mt-checker-missing-' . uniqid('', true);
        mkdir($base, 0775, true);

        try {
            $checker = new TenantExistenceChecker($base);
            $this->expectException(TenantException::class);
            $this->expectExceptionMessage('Tenant not found: missing-id');
            $checker->assertResolvable('missing-id', null);
        } finally {
            (new Filesystem())->remove($base);
        }
    }

    public function testUnknownShortnameThrows(): void
    {
        $base = sys_get_temp_dir() . '/mt-checker-sn-' . uniqid('', true);
        mkdir($base, 0775, true);

        try {
            $registry = TenantShortnameRegistry::fromApplicationDataPath($base);
            $checker = new TenantExistenceChecker($base, $registry);
            $this->expectException(TenantException::class);
            $this->expectExceptionMessage('Unknown tenant: bad-slug');
            $checker->assertResolvable(null, 'bad-slug');
        } finally {
            (new Filesystem())->remove($base);
        }
    }

    public function testValidShortnameResolvesAndChecksFolder(): void
    {
        $base = sys_get_temp_dir() . '/mt-checker-ok-sn-' . uniqid('', true);
        mkdir($base, 0775, true);

        try {
            $registry = TenantShortnameRegistry::fromApplicationDataPath($base);
            $registry->register('tid-slug', 'acme');
            $this->ensureTenantDirectory($base, 'tid-slug');

            $checker = new TenantExistenceChecker($base, $registry);
            $this->assertSame('tid-slug', $checker->assertResolvable(null, 'acme'));
        } finally {
            (new Filesystem())->remove($base);
        }
    }
}
