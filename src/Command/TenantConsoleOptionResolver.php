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
        $idStr = is_string($id) ? trim($id) : '';
        $snStr = is_string($shortname) ? trim($shortname) : '';

        if ($idStr === '' && $snStr === '') {
            throw new InvalidArgumentException('Provide --tenant_id.');
        }

        if ($idStr === '' && $snStr !== '') {
            throw new InvalidArgumentException('--tenant_shortname requires --tenant_id.');
        }

        $registry = TenantShortnameRegistry::tryCreateFromEnv();
        if ($registry === null) {
            throw new RuntimeException(
                'DATA_PATH must be set to register tenant shortname mapping in tenants/tenants.sqlite.'
            );
        }

        $shortnameToRegister = $snStr !== '' ? $snStr : $idStr;
        $registry->register($idStr, $shortnameToRegister);

        return $idStr;
    }
}
