<?php

namespace Phabalicious\Scaffolder\Callbacks\FileContentsHandler;

interface FileContentsHandlerInterface
{
    public function handleContents(string &$file_name, string $content, HandlerOptions $options): string;
}
