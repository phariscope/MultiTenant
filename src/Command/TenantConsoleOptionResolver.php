<?php

declare(strict_types=1);

namespace Phariscope\MultiTenant\Command;

use InvalidArgumentException;
use Phariscope\MultiTenant\Infrastructure\TenantShortname\TenantShortnameRegistry;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;

final class TenantConsoleOptionResolver
{
    public static function resolveTenantId(InputInterface $input): string
    {
        $id = $input->getOption('tenant_id');
        $shortname = $input->getOption('tenant_shortname');
        $tenantIdString = is_string($id) ? trim($id) : '';
        $shortnameString = is_string($shortname) ? trim($shortname) : '';

        if ($tenantIdString === '' && $shortnameString === '') {
            throw new InvalidArgumentException('Provide --tenant_id.');
        }

        if ($tenantIdString === '') {
            throw new InvalidArgumentException('--tenant_shortname requires --tenant_id.');
        }

        return $tenantIdString;
    }

    /**
     * @return non-empty-string
     */
    public static function registerShortnameFromInput(InputInterface $input, string $tenantId): string
    {
        $registry = TenantShortnameRegistry::tryCreateFromEnv();
        if ($registry === null) {
            throw new RuntimeException(
                'DATA_PATH must be set to register tenant shortname mapping in tenants/tenants.sqlite.'
            );
        }

        $shortname = $input->getOption('tenant_shortname');
        $shortnameString = is_string($shortname) ? trim($shortname) : '';
        $shortnameToRegister = $shortnameString !== '' ? $shortnameString : $tenantId;

        return $registry->registerUniqueShortname($tenantId, $shortnameToRegister);
    }
}
