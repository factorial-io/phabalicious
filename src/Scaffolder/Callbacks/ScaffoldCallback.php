<?php

namespace Phabalicious\Scaffolder\Callbacks;

use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Scaffolder\Options;
use Phabalicious\Scaffolder\Scaffolder;
use Phabalicious\Utilities\Utilities;
use Symfony\Component\Yaml\Yaml;

class ScaffoldCallback extends BaseCallback implements CallbackInterface
{
    /**
     * @inheritDoc
     */
    public static function getName()
    {
        return 'scaffold';
    }

    /**
     * @inheritDoc
     */
    public static function requires()
    {
        return '3.6';
    }

    /**
     * @inheritDoc
     */
    public function handle(TaskContextInterface $context, ...$arguments)
    {
        if (count($arguments) < 2) {
            throw new \InvalidArgumentException(
                'The scaffold callbacks requires 2 parameters: url, root_folder, and optionally tokens'
            );
        }
        $scaffold_url = array_shift($arguments);
        if ($scaffold_url[0] == "@" && $base_path = $context->getConfigurationService()->getInheritanceBaseUrl()) {
            $scaffold_url = $base_path . substr($scaffold_url, 1);
        }
        $scaffold_root_folder = array_shift($arguments);
        $tokens = Utilities::mergeData($context->get('tokens', []), $this->getTokens($arguments));
        $this->scaffold($context, $scaffold_url, $scaffold_root_folder, $tokens);
    }

    public function scaffold(
        TaskContextInterface $context,
        string $scaffold_url,
        string $scaffold_root_folder,
        $tokens
    ) {
        $tokens['projectFolder'] = basename($scaffold_root_folder);
        $tokens['rootFolder'] = $scaffold_root_folder;

        $options = new Options();
        $options->setAllowOverride(true)
            ->setUseCacheTokens(false);

        if ($existing_options = $context->get('options', false)) {
            /** @var Options $existing_options */
            $options->setDryRun($existing_options->isDryRun());
            $options->setDynamicOptions($existing_options->getDynamicOptions());
        }

        $scaffolder = new Scaffolder($context->getConfigurationService());
        $cloned_context = clone $context;
        $scaffolder->scaffold($scaffold_url, dirname($scaffold_root_folder), $cloned_context, $tokens, $options);
    }

    private function getTokens(array $arguments):array
    {
        $result = [];
        foreach ($arguments as $arg) {
            [$key, $value] = explode("=", $arg, 2);
            $result[$key] = $value;
        }
        return $result;
    }
}
