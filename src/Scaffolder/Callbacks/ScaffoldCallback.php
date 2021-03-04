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
        $scaffold_url = array_shift($arguments);
        $tokens = Utilities::mergeData($context->get('tokens', []), $this->getTokens($arguments));
        $this->scaffold($context, $scaffold_url, $tokens);
    }

    public function scaffold(
        TaskContextInterface $context,
        string $scaffold_url,
        $tokens
    ) {
        $options = new Options();
        $options->setAllowOverride(true)
            ->setUseCacheTokens(false);
        $scaffolder = new Scaffolder($context->getConfigurationService());
        $scaffolder->scaffold($scaffold_url, dirname($tokens['rootFolder']), $context, $tokens, $options);
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
