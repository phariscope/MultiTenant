<?php

namespace Phariscope\MultiTenant\Tests\Doctrine\Tools\FakeModel;

class FakeEntity
{
    public function __construct(public int $id, public string $name)
    {
        $this->id = 1;
        $this->name = 'test';
    }
}
