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
    public static function getName(): string
    {
        return 'alter_yaml_file';
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
            function ($file_name) use ($context) {
                $content = $context->getShell()->getFileContents($file_name, $context);
                return Yaml::parse($content);
            },
            function ($file_name, $data) use ($context) {
                $context->getShell()->putFileContents(
                    $file_name,
                    Yaml::dump($data, 10, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE),
                    $context
                );
            }
        );
    }
}
