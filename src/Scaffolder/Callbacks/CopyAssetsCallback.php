<?php

namespace Phabalicious\Scaffolder\Callbacks;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Utilities\Utilities;
use Twig\Environment;

class CopyAssetsCallback implements CallbackInterface
{

    const IGNORE_SUBFOLDERS_STRATEGY = 'ignoreSubfolders';

    /** @var ConfigurationService */
    protected $configuration;

    /** @var Environment  */
    protected $twig;

    public function __construct(ConfigurationService $configuration, Environment $twig)
    {
        $this->configuration = $configuration;
        $this->twig = $twig;
    }

    /**
     * @inheritDoc
     */
    public static function getName(): string
    {
        return 'copy_assets';
    }

    /**
     * @inheritDoc
     */
    public static function requires(): string
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
     *
     * @throws \Phabalicious\Exception\FabfileNotReadableException
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function copyAssets(
        TaskContextInterface $context,
        string $target_folder,
        string $data_key,
        $limitedForTwigExtension
    ) {
        $shell = $context->getShell();
        if (!$shell->exists($target_folder)) {
            $shell->run(sprintf('mkdir -p %s && chmod 0777 %s', $target_folder, $target_folder));
        }
        $data = $context->get('scaffoldData');
        $ignore_subfolders = $context->get('scaffoldStrategy', 'default') == self::IGNORE_SUBFOLDERS_STRATEGY;
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
            $file_name = $this->getTargetFileName($file_name, $ignore_subfolders);

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
}
