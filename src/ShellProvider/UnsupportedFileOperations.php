<?php

namespace Phabalicious\ShellProvider;

use Phabalicious\Method\TaskContextInterface;

class UnsupportedFileOperations implements FileOperationsInterface
{

    public function getFileContents($filename, TaskContextInterface $context)
    {
        throw new \RuntimeException('getFileContents not supported in this context!');
    }

    public function putFileContents($filename, $data, TaskContextInterface $context)
    {
        throw new \RuntimeException('putFileContents not supported in this context!');
    }

    public function realPath($filename)
    {
        throw new \RuntimeException('realPath not supported in this context!');
    }
}
