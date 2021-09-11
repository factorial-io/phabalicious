<?php

namespace Phabalicious\ShellProvider;

use Phabalicious\Method\TaskContextInterface;

class LocalFileOperations implements FileOperationsInterface
{

    public function getFileContents($filename, TaskContextInterface $context)
    {
        return file_get_contents($filename);
    }

    public function putFileContents($filename, $data, TaskContextInterface $context)
    {
        return file_put_contents($filename, $data);
    }
}
