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
     *
     * @return array
     *   An associative array of filename => file contents.
     */
    public function transform(TaskContextInterface $context, array $files): array;
}
