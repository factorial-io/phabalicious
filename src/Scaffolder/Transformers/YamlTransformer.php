<?php

namespace Phabalicious\Scaffolder\Transformers;

use Phabalicious\Exception\TransformFailedException;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Scaffolder\Callbacks\TransformCallback;
use Phabalicious\ShellProvider\LocalShellProvider;
use Symfony\Component\Yaml\Dumper;
use Symfony\Component\Yaml\Yaml;

abstract class YamlTransformer implements DataTransformerInterface
{

    /**
     * Iterate over a bunch of yaml files.
     *
     * @param TaskContextInterface $context
     * @param array $files
     * @return \Generator
     * @throws TransformFailedException
     */
    protected function iterateOverFiles(TaskContextInterface $context, array $files)
    {
        if ($context->getShell()->getName() !== LocalShellProvider::PROVIDER_NAME) {
            throw new \RuntimeException('YamlTransformer can only work with local shells!');
        }
        $base = $context->get('loaderBase');
        foreach ($files as $file) {
            $filename = $base . '/' . $file;
            if (is_dir($filename)) {
                $contents = array_filter(scandir($filename), function ($fn) {
                    return $fn[0] !== '.';
                });
                $contents = array_map(function ($fn) use ($file) {
                    return $file . '/' . $fn;
                }, $contents);

                foreach ($this->iterateOverFiles($context, $contents) as $data) {
                    yield $data;
                }
            } elseif (in_array(pathinfo($filename, PATHINFO_EXTENSION), ['yml', 'yaml'])) {
                $context->getConfigurationService()->getLogger()->debug(
                    sprintf("Transforming file `%s` ...", $filename)
                );
                $context->setResult(TransformCallback::TRANSFORMER_INPUT_FILENAME, $filename);
                $data = Yaml::parseFile($filename);
                if ($data) {
                    yield $data;
                }
            }
        }
    }

    /**
     * Return result as yaml-data.
     *
     * @param array $result
     * @return array
     */
    protected function asYamlFiles(array $result): array
    {
        $dumper = new Dumper(2);
        return array_map(function ($data) use ($dumper) {
            return $dumper->dump($data, PHP_INT_MAX, 0);
        }, $result);
    }
}
