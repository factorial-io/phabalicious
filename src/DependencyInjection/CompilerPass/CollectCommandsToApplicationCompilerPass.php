<?php

namespace Phabalicious\DependencyInjection\CompilerPass;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class CollectCommandsToApplicationCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $containerBuilder)
    {
        $applicationDefinition = $containerBuilder->getDefinition(Application::class);
        foreach ($containerBuilder->getDefinitions() as $name => $definition) {
            if ($definition->isAbstract()) {
                continue;
            }
            $class = $definition->getClass();
            if ($class && is_a($class, Command::class, true)) {
                $applicationDefinition->addMethodCall('add', [new Reference($name)]);
            }
        }
    }
}
