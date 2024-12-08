<?php

namespace Phabalicious\Scaffolder\Callbacks;

use Phabalicious\Method\FilesMethod;
use Phabalicious\Method\TaskContextInterface;

abstract class CryptoBaseCallback extends BaseCallback
{
    public static function requires(): string
    {
        return '3.7';
    }

    protected function validate(TaskContextInterface $context, $arguments)
    {
        if (3 !== count($arguments)) {
            throw new \RuntimeException($this->getName().' needs exactly 3 arguments: sourceFiles, targetFolder, secretName');
        }
    }

    protected function iterateOverFiles(TaskContextInterface $context, $input)
    {
        $files = FilesMethod::getRemoteFiles($context->getShell(), dirname($input), [basename($input)]);
        $context->io()->progressStart(count($files));
        $context->getConfigurationService()->getLogger()->info(sprintf(
            '%s: Found %d files to work on...',
            $this->getName(),
            count($files)
        ));
        foreach ($files as $file) {
            $context->getConfigurationService()->getLogger()->info(sprintf(
                '%s: Working on `%s`...',
                $this->getName(),
                $file
            ));
            $context->io()->progressAdvance();
            yield dirname($input).'/'.$file;
        }
        $context->io()->progressFinish();
    }
}
