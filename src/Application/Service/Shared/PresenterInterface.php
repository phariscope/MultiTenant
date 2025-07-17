<?php

namespace Phariscope\MultiTenant\Application\Service\Shared;

interface PresenterInterface
{
    public function write(Response $response): void;

    public function read(): mixed;
}
