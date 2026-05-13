<?php

namespace Phariscope\MultiTenant\Tests\Doctrine\Tools;

use Phariscope\MultiTenant\Doctrine\Tools\ParamsConnection;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ParamsConnectionTest extends TestCase
{
    /**
     * Test if the `getParam` method returns the correct value when the key exists.
     */
    public function testGetParamReturnsValueWhenKeyExists(): void
    {
        // Arrange
        $params = [
            'dbname' => 'tenant_db',
            'user' => 'tenant_user',
            'password' => 'secret',
        ];

        // Act
        $result = ParamsConnection::getParam($params, 'dbname');

        // Assert
        $this->assertEquals('tenant_db', $result);
    }

    /**
     * Test if the `getParam` method throws an exception when the key does not exist.
     */
    public function testGetParamThrowsExceptionWhenKeyDoesNotExist(): void
    {
        // Arrange
        $params = [
            'dbname' => 'tenant_db',
            'user' => 'tenant_user',
        ];
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Password not found');

        // Act
        ParamsConnection::getParam($params, 'password');

        // Assert - PHPUnit verifies the exception
    }

    /**
     * Test if the `getParam` method throws the correct exception message.
     */
    public function testGetParamExceptionMessage(): void
    {
        // Arrange
        $params = [
            'dbname' => 'tenant_db',
            'password' => 'secret',
        ];
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User not found');

        // Act
        ParamsConnection::getParam($params, 'user');

        // Assert - PHPUnit verifies the exception
    }
}
