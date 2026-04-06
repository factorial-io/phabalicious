<?php

namespace Phabalicious\DependencyInjection\CompilerPass;

use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\MethodInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class CollectMethodsToFactoryCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $applicationDefinition = $container->getDefinition(MethodFactory::class);
        foreach ($container->getDefinitions() as $name => $definition) {
            if (is_a($definition->getClass(), MethodInterface::class, true)) {
                $applicationDefinition->addMethodCall('addMethod', [new Reference($name)]);
            }
        }
    }
}
