<?php

namespace Phabalicious\Scaffolder\Callbacks;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Utilities\Utilities;

class CopyAssetsCallback implements CallbackInterface
{

    /** @var ConfigurationService */
    protected $configuration;

    /** @var \Twig_Environment  */
    protected $twig;

    public function __construct(ConfigurationService $configuration, \Twig_Environment $twig)
    {
        $this->configuration = $configuration;
        $this->twig = $twig;
    }

    /**
     * @inheritDoc
     */
    public static function getName()
    {
        return 'copy_assets';
    }

    /**
     * @inheritDoc
     */
    public static function requires()
    {
        return '3.4';
    }

    /**
     * @inheritDoc
     */
    public function handle(TaskContextInterface $context, ...$arguments)
    {
        $this->copyAssets($context, $arguments[0], $arguments[1] ?? 'assets', $arguments[2] ?? false);
    }

    /**
     * @param TaskContextInterface $context
     * @param string $target_folder
     * @param string $data_key
     * @param bool $limitedForTwigExtension
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function copyAssets(
        TaskContextInterface $context,
        $target_folder,
        $data_key,
        $limitedForTwigExtension
    ) {
        if (!is_dir($target_folder)) {
            mkdir($target_folder, 0777, true);
        }
        $data = $context->get('scaffoldData');
        $tokens = $context->get('tokens');
        $is_remote = substr($data['base_path'], 0, 4) == 'http';
        $replacements = Utilities::getReplacements($tokens);

        if (empty($data[$data_key])) {
            throw new \InvalidArgumentException('Scaffold-data does not contain ' . $data_key);
        }

        $context->io()->comment(sprintf('Copying assets `%s`', $data_key));
        $use_progress = count($data[$data_key]) > 3;

        if ($use_progress) {
            $context->io()->progressStart(count($data[$data_key]));
        }

        foreach ($data[$data_key] as $file_name) {
            $tmp_target_file = false;
            if ($is_remote) {
                $tmpl = $this->configuration->readHttpResource($data['base_path'] . '/' . $file_name);
                if ($tmpl === false) {
                    throw new \RuntimeException('Could not read remote asset: '. $data['base_path'] . '/' . $file_name);
                }
                $tmp_target_file = '/tmp/' . $file_name;
                if (!is_dir(dirname($tmp_target_file))) {
                    mkdir(dirname($tmp_target_file), 0777, true);
                }
                file_put_contents('/tmp/' . $file_name, $tmpl);
            }

            if ($limitedForTwigExtension &&
                ('.' . pathinfo($file_name, PATHINFO_EXTENSION) !== $limitedForTwigExtension)
            ) {
                $converted = file_get_contents($context->get('loaderBase') . '/' . $file_name);
            } else {
                $converted = $this->twig->render($file_name, $tokens);
            }

            if ($limitedForTwigExtension) {
                $file_name = str_replace($limitedForTwigExtension, '', $file_name);
            }

            if ($tmp_target_file) {
                unlink($tmp_target_file);
            }

            $file_name = strtr($file_name, $replacements);
            if (strpos($file_name, '/') !== false) {
                $file_name = substr($file_name, strpos($file_name, '/', 1) + 1);
            }

            $target_file_path = $target_folder . '/' . $file_name;
            if (!is_dir(dirname($target_file_path))) {
                mkdir(dirname($target_file_path), 0777, true);
            }

            if ($use_progress) {
                $context->io()->progressAdvance();
            }
            file_put_contents($target_file_path, $converted);
        }
        if ($use_progress) {
            $context->io()->progressFinish();
        }
    }
}
