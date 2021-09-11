<?php

namespace Phabalicious\ShellProvider;

use Phabalicious\Configuration\HostConfig;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Utilities\Utilities;

class DeferredFileOperations implements FileOperationsInterface
{
    /**
     * @var \Phabalicious\ShellProvider\ShellProviderInterface
     */
    private $shell;

    public function __construct(ShellProviderInterface $shell)
    {
        $this->shell = $shell;
    }

    public function getFileContents($filename, TaskContextInterface $context)
    {
        $tmp_file_name = tempnam(
            $this->shell->getHostConfig()->get('tmpFolder', '/tmp'),
            Utilities::getTempNamePrefix($this->shell->getHostConfig()) . '-' . basename($filename)
        );
        $this->shell->getFile($filename, $tmp_file_name, $context);
        $result =  file_get_contents($tmp_file_name);
        @unlink($tmp_file_name);
        return $result;
    }

    public function putFileContents($filename, $data, TaskContextInterface $context)
    {
        $tmp_file_name = tempnam(
            $this->shell->getHostConfig()->get('tmpFolder', '/tmp'),
            Utilities::getTempNamePrefix($this->shell->getHostConfig()) . '-' . basename($filename)
        );
        $result =  file_put_contents($tmp_file_name, $data);
        $this->shell->putFile($tmp_file_name, $filename, $context);
        @unlink($tmp_file_name);

        return $result;
    }
}
