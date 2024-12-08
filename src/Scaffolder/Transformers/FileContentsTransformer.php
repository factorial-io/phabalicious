<?php

namespace Phabalicious\Scaffolder\Transformers;

use Phabalicious\Exception\TransformFailedException;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Scaffolder\Callbacks\TransformCallback;
use Phabalicious\ShellProvider\LocalShellProvider;

abstract class FileContentsTransformer implements DataTransformerInterface, FileContentsTransformerInterface
{
    /**
     * Iterate over a bunch of yaml files.
     *
     * @throws TransformFailedException
     */
    protected function iterateOverFiles(TaskContextInterface $context, array $files): \Generator
    {
        if (LocalShellProvider::PROVIDER_NAME !== $context->getShell()->getName()) {
            throw new \RuntimeException('FileContentsTransformer can only work with local shells!');
        }
        $base = $context->get('rootPath');
        foreach ($files as $file) {
            $filename = realpath($base.'/'.$file);
            if (!$filename) {
                $context->io()->error(sprintf(
                    'could not locate `%s` in `%s`',
                    $base.'/'.$file,
                    getcwd()
                ));

                return;
            }
            if (is_dir($filename)) {
                $contents = array_filter(scandir($filename), function ($fn) {
                    return '.' !== $fn[0];
                });
                $contents = array_map(function ($fn) use ($file) {
                    return $file.'/'.$fn;
                }, $contents);

                foreach ($this->iterateOverFiles($context, $contents) as $data) {
                    yield $data;
                }
            } elseif ($this->appliesTo($filename)) {
                $context->getConfigurationService()->getLogger()->debug(
                    sprintf('Transforming file `%s` ...', $filename)
                );
                $context->setResult(TransformCallback::TRANSFORMER_INPUT_FILENAME, $filename);
                $data = $this->readFile($filename);
                if ($data) {
                    yield $data;
                }
            }
        }
    }
}
