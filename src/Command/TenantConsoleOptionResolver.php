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

        if ($idStr !== '' && $snStr !== '') {
            throw new InvalidArgumentException('Provide either --tenant_id or --tenant_shortname, not both.');
        }

        if ($idStr === '' && $snStr === '') {
            throw new InvalidArgumentException('Provide --tenant_id or --tenant_shortname.');
        }

        if ($idStr !== '') {
            return $idStr;
        }

        $registry = TenantShortnameRegistry::tryCreateFromEnv();
        if ($registry === null) {
            throw new RuntimeException('DATA_PATH must be set to resolve --tenant_shortname.');
        }

        $resolved = $registry->resolveTenantId($snStr);
        if ($resolved === null) {
            throw new InvalidArgumentException(sprintf('Unknown tenant_shortname "%s".', $snStr));
        }

        return $resolved;
    }
}
