<?php

namespace Phabalicious\Scaffolder\Callbacks\FileContentsHandler;

use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Scaffolder\Callbacks\CopyAssetsBaseCallback;
use Phabalicious\Utilities\Utilities;

class HandlerOptions
{

    /**
     * @var bool
     */
    private bool $ignoreSubfolders;

    private $tokens;

    /**
     * @var bool
     */
    private bool $isRemote;

    /**
     * @var array
     */
    private array $replacements;

    private $apply_twig_to_file_w_extension;

    /**
     * @var array
     */
    private array $data;

    /**
     * @var mixed
     */
    private $basePath;

    private $twigRootPath;

    public function __construct(TaskContextInterface $context, array $data)
    {

        $this->ignoreSubfolders = $context->get(
            'scaffoldStrategy',
            'default'
        ) === CopyAssetsBaseCallback::IGNORE_SUBFOLDERS_STRATEGY;
        $this->data = $data;
        $this->tokens = $context->get('tokens');
        $this->isRemote = Utilities::isHttpUrl($data['base_path']);
        $this->replacements = Utilities::getReplacements($this->tokens);
        $this->basePath = $data['base_path'];
    }

    /**
     * @return bool
     */
    public function ignoreSubfolders(): bool
    {
        return $this->ignoreSubfolders;
    }

    /**
     * @return mixed
     */
    public function getTokens(): mixed
    {
        return $this->tokens;
    }

    /**
     * @return bool
     */
    public function isRemote(): bool
    {
        return $this->isRemote;
    }

    /**
     * @return array
     */
    public function getReplacements(): array
    {
        return $this->replacements;
    }

    public function setApplyTwigToFileExtension($value): HandlerOptions
    {
        $this->apply_twig_to_file_w_extension = $value;
        return $this;
    }

    /**
     * @return mixed
     */
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

    /**
     * @return mixed
     */
    public function getBasePath(): mixed
    {
        return $this->basePath;
    }

    public function getTwigRootPath()
    {
        return $this->twigRootPath;
    }

    /**
     * @param mixed $root_path
     *
     * @return HandlerOptions
     */
    public function setTwigRootPath($root_path): HandlerOptions
    {
        $this->twigRootPath = $root_path;
        return $this;
    }
}
