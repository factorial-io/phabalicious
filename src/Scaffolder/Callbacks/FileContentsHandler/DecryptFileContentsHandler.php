<?php

namespace Phabalicious\Scaffolder\Callbacks\FileContentsHandler;

use Defuse\Crypto\Crypto;
use Phabalicious\Configuration\ConfigurationService;
use Twig\Environment;

class DecryptFileContentsHandler extends BaseFileContentsHandler
{
    /**
     * @var \Twig\Environment
     */
    private $secret;

    /**
   * @param \Twig\Environment $twig
   */
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
        $content = str_replace("\n", "", strstr($content, "\n", false));

        return Crypto::decryptWithPassword($content, $secret);
    }
}
