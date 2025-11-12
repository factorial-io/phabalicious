<?php

namespace Phabalicious\Scaffolder\Callbacks;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Scaffolder\Callbacks\FileContentsHandler\TwigFileContentsHandler;
use Twig\Environment;

class CopyAssetsCallback extends CopyAssetsBaseCallback
{
    public function __construct(ConfigurationService $configuration, Environment $twig)
    {
        parent::__construct($configuration, $twig);
        $this->addNewFileContentsHandler(new TwigFileContentsHandler($twig));
    }

    public static function getName(): string
    {
        return 'copy_assets';
    }

    public static function requires(): string
    {
        return '3.4';
    }

    public function handle(TaskContextInterface $context, ...$arguments)
    {
        $this->copyAssets($context, $arguments[0], $arguments[1] ?? 'assets', $arguments[2] ?? false);
    }
}
