<?php

namespace Phariscope\MultiTenant\Share;

use Exception;

class DataPathException extends Exception
{
    public function __construct(
        string $message
    ) {
        parent::__construct($message);
    }
}
