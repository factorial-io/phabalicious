<?php

namespace Phabalicious\Scaffolder\Callbacks\FileContentsHandler;

use Phabalicious\Configuration\ConfigurationService;
use Twig\Environment;

class TwigFileContentsHandler extends BaseFileContentsHandler
{
    /**
     * @var \Twig\Environment
     */
    private $twig;

    /**
   * @param \Twig\Environment $twig
   */
    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    public function handleContents(string &$file_name, string $content, HandlerOptions $options): string
    {
        $ext = $options->getApplyTwigToFileExtension();
        if (!$ext || ('.' . pathinfo($file_name, PATHINFO_EXTENSION) === $ext)) {
            $this->createTempFile($file_name, $content, $options);
            $content = $this->twig->render($file_name, $options->getTokens());
            $this->cleanup();
        }

        if ($ext = $options->getApplyTwigToFileExtension()) {
            $file_name = str_replace($ext, '', $file_name);
        }

        return $content;
    }
}
