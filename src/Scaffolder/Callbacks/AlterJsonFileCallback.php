<?php

namespace Phabalicious\Scaffolder\Callbacks;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Utilities\Utilities;

class AlterJsonFileCallback implements CallbackInterface
{
    /**
     * @inheritDoc
     */
    public static function getName()
    {
        return 'alter_json_file';
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
        $this->alterJsonFile($context, $arguments[0], $arguments[1]);
    }

    public function alterJsonFile(
        TaskContextInterface $context,
        $json_file_name,
        $data_key
    ) {

        $data = $context->get('scaffoldData');
        $tokens = $context->get('tokens');
        $json_file_path = $tokens['rootFolder'] . '/' . $json_file_name;
        if (!file_exists($json_file_path)) {
            $context->io()->warning('Could not find json file ' . $json_file_path);
            return;
        }
        $json = json_decode(file_get_contents($json_file_path), true);
        if (isset($data[$data_key])) {
            $json = Utilities::mergeData($json, $data[$data_key]);
            file_put_contents($json_file_path, json_encode($json, JSON_PRETTY_PRINT));
        }
    }
}
