<?php

namespace Phabalicious\Scaffolder\Callbacks;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Scaffolder\Callbacks\FileContentsHandler\FileContentsHandlerInterface;
use Phabalicious\Scaffolder\Callbacks\FileContentsHandler\HandlerOptions;
use Twig\Environment;

abstract class CopyAssetsBaseCallback implements CallbackInterface
{

    const IGNORE_SUBFOLDERS_STRATEGY = 'ignoreSubfolders';

    /** @var ConfigurationService */
    protected $configuration;

    /** @var Environment  */
    protected $twig;

    protected $fileContentsHandler = [];

    public function __construct(ConfigurationService $configuration, Environment $twig)
    {
        $this->configuration = $configuration;
        $this->twig = $twig;
    }



    /**
     * @param TaskContextInterface $context
     * @param string $target_folder
     * @param string $data_key
     * @param $apply_twig_to_files_w_extension
     *
     * @throws \Phabalicious\Exception\FabfileNotReadableException
     */
    public function copyAssets(
        TaskContextInterface $context,
        string $target_folder,
        string $data_key,
        $apply_twig_to_files_w_extension
    ) {
        $shell = $context->getShell();
        if (!$shell->exists($target_folder)) {
            $shell->run(sprintf('mkdir -p %s && chmod 0777 %s', $target_folder, $target_folder));
        }

        $handler_options = new HandlerOptions($context, $context->get('scaffoldData'));
        $handler_options
            ->setApplyTwigToFileExtension($apply_twig_to_files_w_extension)
            ->setTwigRootPath($context->get('twigRootPath'));

        if (empty($handler_options->get($data_key))) {
            throw new \InvalidArgumentException('Scaffold-data does not contain ' . $data_key);
        }

        $context->io()->comment(sprintf('Copying assets `%s`', $data_key));
        $use_progress = $handler_options->count($data_key) > 3;

        if ($use_progress) {
            $context->io()->progressStart($handler_options->count($data_key));
        }

        foreach ($handler_options->get($data_key) as $file_name) {
            $tmp_target_file = false;
            if ($handler_options->isRemote()) {
                $url = $handler_options->get('base_path') . '/' . $file_name;
                $tmpl = $this->configuration->readHttpResource($url);
                if ($tmpl === false) {
                    throw new \RuntimeException(sprintf(
                        'Could not read remote asset: `%s`!',
                        $url
                    ));
                }
            } else {
                $tmpl = file_get_contents($handler_options->getBasePath() . '/' .$file_name);
            }

            /** @var FileContentsHandlerInterface $handler */
            $converted = $tmpl;
            foreach ($this->fileContentsHandler as $handler) {
                $converted = $handler->handleContents($file_name, $converted, $handler_options);
            }

            if ($ext = $handler_options->getApplyTwigToFileExtension()) {
                $file_name = str_replace($ext, '', $file_name);
            }

            $file_name = strtr($file_name, $handler_options->getReplacements());
            $file_name = $this->getTargetFileName($file_name, $handler_options->ignoreSubfolders());

            $target_file_path = $target_folder . '/' . $file_name;

            $p = dirname($target_file_path);
            if (!$shell->exists($p)) {
                $shell->run(sprintf('mkdir -p %s && chmod 0777 %s', $p, $p));
            }

            $this->configuration->getLogger()->debug(sprintf("Scaffolding file '%s'", $target_file_path));

            $shell->putFileContents($target_file_path, $converted, $context);

            if ($use_progress) {
                $context->io()->progressAdvance();
            }
        }
        if ($use_progress) {
            $context->io()->progressFinish();
        }
    }

    /**
     * @param string $file_name
     * @param bool $ignore_subfolders
     *
     * @return false|string
     */
    protected function getTargetFileName(string $file_name, bool $ignore_subfolders)
    {
        if (strpos($file_name, '/') !== false) {
            if ($ignore_subfolders) {
                $file_name = basename($file_name);
            } else {
                $file_name = substr($file_name, strpos($file_name, '/', 1) + 1);
            }
        }
        return $file_name;
    }

    public function addNewFileContentsHandler(FileContentsHandlerInterface $handler)
    {
        $this->fileContentsHandler[] = $handler;
    }
}
