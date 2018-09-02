<?php
namespace Phabalicious;

use Phabalicious\DependencyInjection\CompilerPass\CollectCommandsToApplicationCompilerPass;
use Phabalicious\DependencyInjection\CompilerPass\CollectMethodsToFactoryCompilerPass;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel;

class AppKernel extends Kernel
{
    public function __construct()
    {
        // these values allows container rebuild when config changes
        parent::__construct('dev', true);
    }
    /**
     * @return BundleInterface[]
     */
    public function registerBundles()
    {
        return [];
    }
    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(__DIR__ . '/../config/services.yml');
    }
    /**
     * Unique cache path for this Kernel
     */
    public function getCacheDir()
    {
        return sys_get_temp_dir() . '/phabalicious' . md5(self::class);
    }
    /**
     * Unique logs path for this Kernel
     */
    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/phabalicious' . md5(self::class);
    }

    protected function build(ContainerBuilder $containerBuilder): void
    {
        $containerBuilder->addCompilerPass(new CollectCommandsToApplicationCompilerPass());
        $containerBuilder->addCompilerPass(new CollectMethodsToFactoryCompilerPass());
    }
}