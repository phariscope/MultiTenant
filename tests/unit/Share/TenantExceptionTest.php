<?php

declare(strict_types=1);

namespace Phariscope\MultiTenant\Tests\Share;

use Phariscope\MultiTenant\Share\TenantException;
use PHPUnit\Framework\TestCase;

final class TenantExceptionTest extends TestCase
{
    public function testUnknownTenantIdHasDefaultExceptionCodeZero(): void
    {
        $exception = TenantException::unknownTenantId('missing');

        $this->assertSame(0, $exception->getCode());
        $this->assertSame('missing', $exception->getTenantIdValue());
    }

    public function testUnknownShortnameStoresShortname(): void
    {
        $exception = TenantException::unknownShortname('bad-slug');

        $this->assertSame(0, $exception->getCode());
        $this->assertSame('bad-slug', $exception->getTenantShortnameValue());
    }
}
