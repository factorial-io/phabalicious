<?php

namespace Phabalicious\Scaffolder\Callbacks;

use Phabalicious\Method\TaskContextInterface;

class SetDirectoryCallback implements CallbackInterface
{
    public static function getName(): string
    {
        return 'set_directory';
    }

    public static function requires(): string
    {
        return '3.4';
    }

    public function handle(TaskContextInterface $context, ...$arguments)
    {
        $this->setDirectory($context, $arguments[0]);
    }

    public function setDirectory(
        TaskContextInterface $context,
        $directory,
    ) {
        $shell = $context->getShell();
        if (!$shell->exists($directory)) {
            throw new \RuntimeException(sprintf('Directory %s does not exist!', $directory));
        }
        $shell->cd($directory);
        $context->io()->comment(sprintf('Changed directory to %s', $directory));
    }
}
