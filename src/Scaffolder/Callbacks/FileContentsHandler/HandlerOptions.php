<?php

namespace Phabalicious\Scaffolder\Callbacks\FileContentsHandler;

use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Scaffolder\Callbacks\CopyAssetsBaseCallback;
use Phabalicious\Utilities\Utilities;

class HandlerOptions
{
    private bool $ignoreSubfolders;

    private $tokens;

    private bool $isRemote;

    private array $replacements;

    private $apply_twig_to_file_w_extension;

    private array $data;

    private $basePath;

    private $twigRootPath;

    public function __construct(TaskContextInterface $context, array $data)
    {
        $this->ignoreSubfolders = CopyAssetsBaseCallback::IGNORE_SUBFOLDERS_STRATEGY === $context->get(
            'scaffoldStrategy',
            'default'
        );
        $this->data = $data;
        $this->tokens = $context->get('tokens');
        $this->isRemote = Utilities::isHttpUrl($data['base_path']);
        $this->replacements = Utilities::getReplacements($this->tokens);
        $this->basePath = $data['base_path'];
    }

    public function ignoreSubfolders(): bool
    {
        return $this->ignoreSubfolders;
    }

    public function getTokens(): mixed
    {
        return $this->tokens;
    }

    public function isRemote(): bool
    {
        return $this->isRemote;
    }

    public function getReplacements(): array
    {
        return $this->replacements;
    }

    public function setApplyTwigToFileExtension($value): HandlerOptions
    {
        $this->apply_twig_to_file_w_extension = $value;

        return $this;
    }

    public function getApplyTwigToFileExtension(): mixed
    {
        return $this->apply_twig_to_file_w_extension;
    }

    public function get(string $data_key)
    {
        return $this->data[$data_key] ?? null;
    }

    public function count($data_key): ?int
    {
        return count($this->data[$data_key]);
    }

    public function getBasePath(): mixed
    {
        return $this->basePath;
    }

    public function getTwigRootPath()
    {
        return $this->twigRootPath;
    }

    public function setTwigRootPath($root_path): HandlerOptions
    {
        $this->twigRootPath = $root_path;

        return $this;
    }
}
