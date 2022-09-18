<?php

namespace Phabalicious\Scaffolder\Transformers;

interface FileContentsTransformerInterface
{
    public function appliesTo(string $filename): bool;

    public function readFile(string $filename): array;
}
