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
        $params = [
            'dbname' => 'tenant_db',
            'user' => 'tenant_user',
            'password' => 'secret'
        ];

        $result = ParamsConnection::getParam($params, 'dbname');
        $this->assertEquals('tenant_db', $result);
    }

    /**
     * Test if the `getParam` method throws an exception when the key does not exist.
     */
    public function testGetParamThrowsExceptionWhenKeyDoesNotExist(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Password not found');

        $params = [
            'dbname' => 'tenant_db',
            'user' => 'tenant_user'
        ];

        ParamsConnection::getParam($params, 'password');
    }

    /**
     * Test if the `getParam` method throws the correct exception message.
     */
    public function testGetParamExceptionMessage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User not found');

        $params = [
            'dbname' => 'tenant_db',
            'password' => 'secret'
        ];

        ParamsConnection::getParam($params, 'user');
    }
}
