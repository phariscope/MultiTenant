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
        // Arrange
        $sut = new PresenterStandardResponse();
        $response = new ResponseFake();

        // Act
        $sut->write($response);
        $result = $sut->read();

        // Assert
        $this->assertInstanceOf(PresenterInterface::class, $sut);
        $this->assertInstanceOf(Response::class, $result);
    }
}
