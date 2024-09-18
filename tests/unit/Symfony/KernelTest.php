<?php

namespace Phariscope\MultiTenant\Tests\Symfony;

use Phariscope\MultiTenant\Symfony\Kernel;
use PHPUnit\Framework\TestCase;

class KernelTest extends TestCase
{
    public function testConstruct(): void
    {

        $kernel = new Kernel("test", true);
        $this->assertInstanceOf(Kernel::class, $kernel);
        $this->assertTrue($kernel->isDebug());
    }
}
