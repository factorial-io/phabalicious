<?php

namespace Phabalicious\ShellProvider;

use Phabalicious\Method\TaskContextInterface;

class NoopFileOperations implements FileOperationsInterface
{
    public function getFileContents($filename, TaskContextInterface $context)
    {
        return file_get_contents($filename);
    }

    public function putFileContents($filename, $data, TaskContextInterface $context)
    {
        return false;
    }

    public function realPath($filename): string|false
    {
        return $filename;
    }
}
