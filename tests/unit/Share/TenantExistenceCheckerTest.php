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
            $this->assertNull($checker->assertResolvableForHttp(null, null));
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
            $this->assertSame('tid-ok', $checker->assertResolvableForHttp('tid-ok', null));
        } finally {
            (new Filesystem())->remove($base);
        }
    }

    public function testUnknownTenantIdThrowsWhenFolderMissing(): void
    {
        $base = sys_get_temp_dir() . '/mt-checker-missing-' . uniqid('', true);
        mkdir($base, 0775, true);

        try {
            $checker = new TenantExistenceChecker($base);
            $this->expectException(TenantException::class);
            $this->expectExceptionMessage('Tenant not found: missing-id');
            $checker->assertResolvableForHttp('missing-id', null);
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
            $checker->assertResolvableForHttp(null, 'bad-slug');
        } finally {
            (new Filesystem())->remove($base);
        }
    }

    public function testValidShortnameResolvesWithoutFolderCheck(): void
    {
        $base = sys_get_temp_dir() . '/mt-checker-ok-sn-' . uniqid('', true);
        mkdir($base, 0775, true);

        try {
            $registry = TenantShortnameRegistry::fromApplicationDataPath($base);
            $registry->register('tid-slug', 'acme');

            $checker = new TenantExistenceChecker($base, $registry);
            $this->assertSame('tid-slug', $checker->assertResolvableForHttp(null, 'acme'));
        } finally {
            (new Filesystem())->remove($base);
        }
    }

    public function testBothProvidedValidatesFolderAndRegistry(): void
    {
        $base = sys_get_temp_dir() . '/mt-checker-both-' . uniqid('', true);
        mkdir($base, 0775, true);

        try {
            $registry = TenantShortnameRegistry::fromApplicationDataPath($base);
            $registry->register('tid-both', 'slug-both');
            $this->ensureTenantDirectory($base, 'tid-both');

            $checker = new TenantExistenceChecker($base, $registry);
            $this->assertSame('tid-both', $checker->assertResolvableForHttp('tid-both', 'slug-both'));
        } finally {
            (new Filesystem())->remove($base);
        }
    }

    public function testBothProvidedThrowsWhenShortnameDoesNotMatchTenantId(): void
    {
        $base = sys_get_temp_dir() . '/mt-checker-mismatch-' . uniqid('', true);
        mkdir($base, 0775, true);

        try {
            $registry = TenantShortnameRegistry::fromApplicationDataPath($base);
            $registry->register('tid-a', 'slug-a');
            $this->ensureTenantDirectory($base, 'tid-b');

            $checker = new TenantExistenceChecker($base, $registry);
            $this->expectException(TenantException::class);
            $this->expectExceptionMessage('Tenant shortname "slug-a" does not match tenant id "tid-b".');
            $checker->assertResolvableForHttp('tid-b', 'slug-a');
        } finally {
            (new Filesystem())->remove($base);
        }
    }

    public function testBothProvidedThrowsWhenShortnameUnknown(): void
    {
        $base = sys_get_temp_dir() . '/mt-checker-both-sn-' . uniqid('', true);
        mkdir($base, 0775, true);

        try {
            TenantShortnameRegistry::fromApplicationDataPath($base);
            $this->ensureTenantDirectory($base, 'tid-only');

            $checker = new TenantExistenceChecker($base);
            $this->expectException(TenantException::class);
            $this->expectExceptionMessage('Unknown tenant: unknown-slug');
            $checker->assertResolvableForHttp('tid-only', 'unknown-slug');
        } finally {
            (new Filesystem())->remove($base);
        }
    }
}
