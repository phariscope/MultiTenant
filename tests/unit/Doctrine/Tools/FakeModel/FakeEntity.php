<?php

namespace Phariscope\MultiTenant\Tests\Doctrine\Tools\FakeModel;

class FakeEntity
{
    public int $id;
    public string $name;

    public function __construct(int $id, string $name)
    {
        $this->id = $id;
        $this->name = $name;
    }
}
