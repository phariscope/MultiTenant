<?php

namespace Phariscope\MultiTenant\Doctrine\Sqlite;

use Exception;

class DataPathException extends Exception
{
    public function __construct(
        string $message
    ) {
        parent::__construct($message);
    }
}
