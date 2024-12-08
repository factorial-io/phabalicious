<?php

namespace Phabalicious\ShellProvider;

use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Utilities\Utilities;

class DeferredFileOperations implements FileOperationsInterface
{
    /**
     * @var ShellProviderInterface
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
            Utilities::getTempNamePrefix($this->shell->getHostConfig()).'-'.basename($filename)
        );
        $this->shell->getFile($filename, $tmp_file_name, $context);
        $result = file_get_contents($tmp_file_name);
        @unlink($tmp_file_name);

        return $result;
    }

    public function putFileContents($filename, $data, TaskContextInterface $context)
    {
        $tmp_file_name = tempnam(
            $this->shell->getHostConfig()->get('tmpFolder', '/tmp'),
            Utilities::getTempNamePrefix($this->shell->getHostConfig()).'-'.basename($filename)
        );
        $result = file_put_contents($tmp_file_name, $data);
        $this->shell->putFile($tmp_file_name, $filename, $context);
        @unlink($tmp_file_name);

        return $result;
    }

    public function realPath($filename): string|false
    {
        $result = $this->shell->run(sprintf('realpath %s', $filename), RunOptions::CAPTURE_AND_HIDE_OUTPUT, false);
        if ($result->failed() || count($result->getOutput()) < 1) {
            return false;
        }

        return $result->getOutput()[0];
    }
}
