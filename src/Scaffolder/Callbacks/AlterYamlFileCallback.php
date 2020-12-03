<?php

namespace Phabalicious\Scaffolder\Callbacks;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Utilities\Utilities;
use Symfony\Component\Yaml\Yaml;

class AlterYamlFileCallback extends BaseCallback implements CallbackInterface
{
    /**
     * @inheritDoc
     */
    public static function getName()
    {
        return 'alter_yaml_file';
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
        $this->alterYamlFile($context, $arguments[0], $arguments[1]);
    }

    public function alterYamlFile(
        TaskContextInterface $context,
        $yaml_file_name,
        $data_key
    ) {
        $this->alterFile(
            $context,
            $yaml_file_name,
            $data_key,
            function ($file_name) {
                return Yaml::parseFile($file_name);
            },
            function ($file_name, $data) {
                file_put_contents($file_name, Yaml::dump($data, 10, 2));
            }
        );
    }
}
