<?php

namespace Phabalicious\Scaffolder;

use Phabalicious\Method\TaskContextInterface;

interface DataTransformerInterface
{
    public static function getName();

    public function transform(TaskContextInterface $context, array $files);
}
