<?php

namespace Phariscope\MultiTenant\Tests\Symfony;

use Phariscope\MultiTenant\Symfony\Kernel;
use PHPUnit\Framework\TestCase;

class KernelTest extends TestCase
{
    public function testConstruct(): void
    {
        // Arrange
        // (environment and debug flags)

        // Act
        $kernel = new Kernel('test', true);

        // Assert
        $this->assertInstanceOf(Kernel::class, $kernel);
        $this->assertTrue($kernel->isDebug());
    }
}
