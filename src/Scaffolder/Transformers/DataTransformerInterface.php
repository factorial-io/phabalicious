<?php

namespace Phabalicious\Scaffolder\Transformers;

use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Utilities\PluginInterface;

interface DataTransformerInterface extends PluginInterface
{
    /**
     * transforms a bunch of files to another bunch of files.
     *
     * @param TaskContextInterface $context
     *   The current context.
     * @param array $files
     *   The input files.
     * @param string $target_path
     *   The target path where files get written.
     *
     * @return array
     *   An associative array of filename => file contents.
     */
    public function transform(TaskContextInterface $context, array $files, $target_path): array;
}
