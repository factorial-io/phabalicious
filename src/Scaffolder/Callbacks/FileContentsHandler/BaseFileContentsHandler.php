<?php

namespace Phabalicious\Scaffolder\Callbacks\FileContentsHandler;

use _HumbugBox8d4774cb1aad\Amp\Process\Internal\Posix\Handle;
use Phabalicious\Configuration\ConfigurationService;
use Twig\Environment;

abstract class BaseFileContentsHandler implements FileContentsHandlerInterface
{
    protected $tmpFile = false;

    protected function createTempFile(string $file_name, $content, HandlerOptions $options)
    {

        $this->tmpFile = $options->getTwigRootPath() .  '/' . $file_name;
        if (!is_dir(dirname($this->tmpFile))) {
            mkdir(dirname($this->tmpFile), 0777, true);
        }
        file_put_contents($this->tmpFile, $content);
    }

    public function cleanup()
    {
        if ($this->tmpFile && file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
            $this->tmpFile = false;
        }
    }
}
