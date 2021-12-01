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

    public function handleContents(string $file_name, string $content, HandlerOptions $options): string
    {
        return Crypto::decryptWithPassword($content, $this->secret, true);
    }
}
