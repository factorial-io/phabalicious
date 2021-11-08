<?php

namespace Phabalicious\Scaffolder\Callbacks;

use Phabalicious\Method\TaskContextInterface;

class AssertFileCallback implements CallbackInterface
{
    /**
     * @inheritDoc
     */
    public static function getName(): string
    {
        return 'assert_file';
    }

    /**
     * @inheritDoc
     */
    public static function requires(): string
    {
        return '3.5.10';
    }

    /**
     * @inheritDoc
     */
    public function handle(TaskContextInterface $context, ...$arguments)
    {
        $this->assertFile($context, $arguments[0], $arguments[1]);
    }

    public function assertFile(
        TaskContextInterface $context,
        $file_path,
        $error_message
    ) {

        if (!$context->getShell()->exists($file_path)) {
            throw new \RuntimeException(sprintf("%s does not exist! %s", $file_path, $error_message));
        }
    }
}
