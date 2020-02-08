<?php

namespace Phabalicious\Scaffolder;

use Phabalicious\Method\TaskContextInterface;
use Symfony\Component\Yaml\Dumper;
use Symfony\Component\Yaml\Yaml;

abstract class YamlTransformer implements DataTransformerInterface
{

    /**
     * Iterate over a bunch of yaml files.
     */
    protected function iterateOverFiles(TaskContextInterface $context, array $files)
    {
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
            } else {
                $data = Yaml::parseFile($filename);
                if ($data) {
                    yield $data;
                }
            }
        }
    }

    /**
     * Returrn result as yaml-data.
     */
    protected function asYamlFiles(array $result)
    {
        $dumper = new Dumper(2);
        return array_map(function ($data) use ($dumper) {
            return $dumper->dump($data, PHP_INT_MAX, 0);
        }, $result);
    }
}
