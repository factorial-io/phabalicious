<?php

namespace Phabalicious\Scaffolder\Callbacks;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Utilities\Utilities;

class SetDirectoryCallback implements CallbackInterface
{
    /**
     * @inheritDoc
     */
    public static function getName()
    {
        return 'set_directory';
    }

    /**
     * @inheritDoc
     */
    public static function requires()
    {
        return '3.4';
    }

    /**
     * @inheritDoc
     */
    public function handle(TaskContextInterface $context, ...$arguments)
    {
        $this->setDirectory($context, $arguments[0]);
    }

    public function setDirectory(
        TaskContextInterface $context,
        $directory
    ) {
        $shell = $context->getShell();
        if (!$shell->exists($directory)) {
            throw new \RuntimeException(sprintf('Directory %s does not exist!', $directory));
        }
        $shell->cd($directory);
        $context->io()->comment(sprintf('Changed directory to %s', $directory));
    }
}
