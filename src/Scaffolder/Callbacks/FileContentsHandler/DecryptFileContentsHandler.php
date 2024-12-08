<?php

namespace Phabalicious\Scaffolder\Callbacks\FileContentsHandler;

use Defuse\Crypto\Crypto;
use Twig\Environment;

class DecryptFileContentsHandler extends BaseFileContentsHandler
{
    /**
     * @var Environment
     */
    private $secret;

    public function __construct(string $secret)
    {
        $this->secret = $secret;
    }

    public function handleContents(string &$file_name, string $content, HandlerOptions $options): string
    {
        $file_name = str_replace('.enc', '', $file_name);

        return self::decryptFileContent($content, $this->secret);
    }

    public static function decryptFileContent($content, $secret)
    {
        $first_line = strstr($content, "\n", true);
        $content = str_replace("\n", '', strstr($content, "\n", false));

        return Crypto::decryptWithPassword($content, $secret);
    }
}
