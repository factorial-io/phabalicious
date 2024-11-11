<?php
namespace Phabalicious;

use Phabalicious\DependencyInjection\CompilerPass\CollectCommandsToApplicationCompilerPass;
use Phabalicious\DependencyInjection\CompilerPass\CollectMethodsToFactoryCompilerPass;
use Phabalicious\Utilities\Utilities;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel;

class AppKernel extends Kernel
{
    /**
     * @return BundleInterface[]
     */
    public function registerBundles(): iterable
    {
        return [];
    }
    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(__DIR__ . '/../config/services.yml');
    }
    /**
     * Unique cache path for this Kernel
     */
    public function getCacheDir(): string
    {
        $dir = implode('-', [
            sys_get_temp_dir() . '/phabalicious',
            Utilities::FALLBACK_VERSION,
            posix_getuid(),
            md5(self::class)
        ]);

        return $dir;
    }
    /**
     * Unique logs path for this Kernel
     */
    public function getLogDir(): string
    {
        return $this->getCacheDir();
    }

    protected function build(ContainerBuilder $containerBuilder): void
    {
        $containerBuilder->addCompilerPass(new CollectCommandsToApplicationCompilerPass());
        $containerBuilder->addCompilerPass(new CollectMethodsToFactoryCompilerPass());
    }
}
