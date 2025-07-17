<?php

namespace Phariscope\MultiTenant\Tests\Application\Service\Shared;

use Phariscope\MultiTenant\Application\Service\Shared\PresenterInterface;
use Phariscope\MultiTenant\Application\Service\Shared\PresenterStandardResponse;
use Phariscope\MultiTenant\Application\Service\Shared\Response;
use PHPUnit\Framework\TestCase;

class PresenterStandardResponseTest extends TestCase
{
    public function testWriteAndRead(): void
    {
        $sut = new PresenterStandardResponse();

        $this->assertInstanceOf(PresenterInterface::class, $sut);
        $response = new ResponseFake();

        $sut->write($response);

        $result = $sut->read();

        $this->assertInstanceOf(Response::class, $result);
    }
}
