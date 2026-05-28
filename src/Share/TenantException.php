<?php

namespace Phariscope\MultiTenant\Share;

use Exception;

class TenantException extends Exception
{
    public function __construct(
        string $message,
        private readonly ?string $tenantId = null,
        private readonly ?string $tenantShortname = null,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getTenantIdValue(): ?string
    {
        return $this->tenantId;
    }

    public function getTenantShortnameValue(): ?string
    {
        return $this->tenantShortname;
    }

    public static function unknownTenantId(string $tenantId): self
    {
        return new self(
            sprintf('Tenant not found: %s', $tenantId),
            tenantId: $tenantId,
        );
    }

    public static function unknownShortname(string $tenantShortname): self
    {
        return new self(
            sprintf('Unknown tenant: %s', $tenantShortname),
            tenantShortname: $tenantShortname,
        );
    }

    public static function cannotResolveShortname(string $tenantShortname): self
    {
        return new self(
            sprintf('Cannot resolve tenant shortname "%s": DATA_PATH is not set.', $tenantShortname),
            tenantShortname: $tenantShortname,
        );
    }

    public static function tenantShortnameMismatch(string $tenantId, string $tenantShortname): self
    {
        return new self(
            sprintf(
                'Tenant shortname "%s" does not match tenant id "%s".',
                $tenantShortname,
                $tenantId,
            ),
            tenantId: $tenantId,
            tenantShortname: $tenantShortname,
        );
    }
}
