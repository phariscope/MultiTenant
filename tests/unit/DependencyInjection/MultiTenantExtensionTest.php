<?php

namespace Phariscope\MultiTenant\Tests\DependencyInjection;

use Phariscope\MultiTenant\DependencyInjection\MultiTenantExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class MultiTenantExtensionTest extends TestCase
{
    public function testLoadMethodLoadsServicesYaml(): void
    {
        $extension = new MultiTenantExtension();
        $containerBuilder = new ContainerBuilder();

        $extension->load([], $containerBuilder);
        $firstResource = $containerBuilder->getResources()[0];
        $this->assertStringEndsWith(
            'src/Resources/config/services.yaml',
            $firstResource
        );
    }
}
