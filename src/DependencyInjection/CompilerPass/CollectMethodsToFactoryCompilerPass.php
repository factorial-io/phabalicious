<?php

namespace Phabalicious\DependencyInjection\CompilerPass;

use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\MethodInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class CollectMethodsToFactoryCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $containerBuilder)
    {
        $applicationDefinition = $containerBuilder->getDefinition(MethodFactory::class);
        foreach ($containerBuilder->getDefinitions() as $name => $definition) {
            if (is_a($definition->getClass(), MethodInterface::class, true)) {
                $applicationDefinition->addMethodCall('addMethod', [new Reference($name)]);
            }
        }
    }
}
