<?php

namespace Phabalicious\Scaffolder\Callbacks\FileContentsHandler;

abstract class BaseFileContentsHandler implements FileContentsHandlerInterface
{
    protected false|string $tmpFile = false;

    protected function createTempFile(string $file_name, $content, HandlerOptions $options): void
    {

        $this->tmpFile = $options->getTwigRootPath() .  '/' . $file_name;
        if (!is_dir(dirname($this->tmpFile)) && !mkdir($concurrentDirectory = dirname($this->tmpFile), 0777, true) && !is_dir($concurrentDirectory)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
        }
        file_put_contents($this->tmpFile, $content);
    }

    public function cleanup(): void
    {
        if ($this->tmpFile && file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
            $this->tmpFile = false;
        }
    }
}
