<?php

namespace Phariscope\MultiTenant\Tests\DependencyInjection;

use Phariscope\MultiTenant\DependencyInjection\MultiTenantExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class MultiTenantExtensionTest extends TestCase
{
    public function testLoadMethodLoadsServicesYaml(): void
    {
        // Arrange
        $extension = new MultiTenantExtension();
        $containerBuilder = new ContainerBuilder();

        // Act
        $extension->load([], $containerBuilder);
        $firstResource = $containerBuilder->getResources()[0];

        // Assert
        $this->assertStringEndsWith(
            'src/Resources/config/services.yaml',
            $firstResource
        );
    }
}
