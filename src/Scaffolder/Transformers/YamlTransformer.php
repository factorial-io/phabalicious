<?php

namespace Phabalicious\Scaffolder\Transformers;

use Symfony\Component\Yaml\Dumper;
use Symfony\Component\Yaml\Yaml;

abstract class YamlTransformer extends FileContentsTransformer
{
    public function appliesTo(string $filename): bool
    {
        return in_array(pathinfo($filename, PATHINFO_EXTENSION), ['yml', 'yaml']);
    }

    public function readFile(string $filename): array
    {
        return Yaml::parseFile($filename);
    }

    /**
     * Return result as yaml-data.
     */
    protected function asYamlFiles(array $result): array
    {
        $dumper = new Dumper(2);

        return array_map(function ($data) use ($dumper) {
            return $dumper->dump($data, PHP_INT_MAX, 0);
        }, $result);
    }
}
