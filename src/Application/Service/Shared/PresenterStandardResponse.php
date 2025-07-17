<?php

namespace Phariscope\MultiTenant\Application\Service\Shared;

class PresenterStandardResponse implements PresenterInterface
{
    private Response $response;

    public function write(Response $response): void
    {
        $this->response = $response;
    }

    public function read(): Response
    {
        return $this->response;
    }
}
