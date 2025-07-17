<?php

namespace Phariscope\MultiTenant\Domain\Model\Tenant;

class TenantId
{
    public const PREFIX = 'am_cl_';

    private string $id;


    public function __construct(?string $defaultId = null)
    {
        $this->id = ($defaultId === null) ?
        self::PREFIX . uniqid() :
        $defaultId;
    }

    public function __toString(): string
    {
        return $this->id;
    }

    public function equals(TenantId $id): bool
    {
        return $this->id === $id->id;
    }
}
