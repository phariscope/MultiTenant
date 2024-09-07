<?php

namespace Phariscope\MultiTenant\Tests\Unit;

use Phariscope\MultiTenant\First;
use PHPUnit\Framework\TestCase;

class FirstTest extends TestCase
{
    public function testFirst(): void
    {
        $sut = new First();
        $this->assertTrue($sut->getTrue());
    }
}
