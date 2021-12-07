<?php

namespace Phabalicious\Scaffolder\Callbacks;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Scaffolder\Callbacks\FileContentsHandler\DecryptFileContentsHandler;
use Phabalicious\Scaffolder\Callbacks\FileContentsHandler\TwigFileContentsHandler;
use Twig\Environment;

class DecryptAssetsCallback extends CopyAssetsBaseCallback
{

    public function __construct(ConfigurationService $configuration, Environment $twig)
    {
        parent::__construct($configuration, $twig);
    }

    /**
     * @inheritDoc
     */
    public static function getName(): string
    {
        return 'decrypt_assets';
    }

    /**
     * @inheritDoc
     */
    public static function requires(): string
    {
        return '3.7';
    }

    public function handle(TaskContextInterface $context, ...$arguments)
    {

        if (count($arguments) !== 4) {
            throw new \RuntimeException('decrypt_asserts needs exactly 4 arguments: '
                . 'targetFolder, dataKey, secretName, twigExtension');
        }

        $secret = $this->configuration->getPasswordManager()->getPasswordFor($arguments[2]);

        $this->clearFileContentsHandlers();
        $this->addNewFileContentsHandler(new DecryptFileContentsHandler($secret));
        $this->addNewFileContentsHandler(new TwigFileContentsHandler($this->twig));

        $this->copyAssets($context, $arguments[0], $arguments[1] ?? 'assets', $arguments[3] ?? false);
    }
}
