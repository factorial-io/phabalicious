<?php

namespace Phabalicious\Scaffolder\Callbacks;

use Phabalicious\Method\TaskContextInterface;

class AlterJsonFileCallback extends BaseCallback implements CallbackInterface
{
    public static function getName(): string
    {
        return 'alter_json_file';
    }

    public static function requires(): string
    {
        return '3.4';
    }

    public function handle(TaskContextInterface $context, ...$arguments)
    {
        $this->alterJsonFile($context, $arguments[0], $arguments[1]);
    }

    public function alterJsonFile(
        TaskContextInterface $context,
        $json_file_name,
        $data_key,
    ) {
        $this->alterFile(
            $context,
            $json_file_name,
            $data_key,
            function ($file_name) use ($context) {
                $content = $context->getShell()->getFileContents($file_name, $context);

                return json_decode($content, true);
            },
            function ($file_name, $data) use ($context) {
                $context->getShell()->putFileContents(
                    $file_name,
                    json_encode($data, JSON_PRETTY_PRINT),
                    $context
                );
            }
        );
    }
}
