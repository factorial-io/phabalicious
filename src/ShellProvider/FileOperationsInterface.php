<?php

namespace Phabalicious\ShellProvider;

use Phabalicious\Method\TaskContextInterface;

interface FileOperationsInterface
{
    public function getFileContents($filename, TaskContextInterface $context);
    public function putFileContents($filename, $data, TaskContextInterface $context);
    public function realPath($filename): string|false;
}
