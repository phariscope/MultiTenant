<?php

namespace Phariscope\MultiTenant\Doctrine\Tools;

use Doctrine\DBAL\DriverManager;

/**
 * @psalm-import-type Params from DriverManager
 */
class ParamsConnection
{
    /**
     * @psalm-param Params $params
     */
    public static function getParam(array $params, string $key): mixed
    {
        if (isset($params[$key])) {
            return $params[$key];
        }

        throw new \InvalidArgumentException(ucfirst($key) . ' not found');
    }
}
