<?php

namespace Phabalicious\Scaffolder\Callbacks;

use Phabalicious\Method\TaskContextInterface;

class GetFileFrom1Password extends BaseCallback implements CallbackInterface
{
    /**
     * @inheritDoc
     */
    public static function getName(): string
    {
        return 'get_file_from_1password';
    }

    /**
     * @inheritDoc
     */
    public static function requires(): string
    {
        return '3.7';
    }

    /**
     * @inheritDoc
     */
    public function handle(TaskContextInterface $context, ...$arguments)
    {
        if (count($arguments) !== 4) {
            throw new \RuntimeException(sprintf(
                '%s requires the follwing arguments: `token_id`, `vault_id`, `item_id`, `taget_file`',
                self::getName()
            ));
        }
        $this->getFileFrom1Password($context, $arguments[0], $arguments[1], $arguments[2], $arguments[3]);
    }

    public function getFileFrom1Password(
        TaskContextInterface $context,
        $token_id,
        $vault_id,
        $item_id,
        $target_file_name
    ) {
        $content = $context->getPasswordManager()->getFileContentFrom1Password(
            $token_id,
            $vault_id,
            $item_id
        );
        if (!$content) {
            throw new \RuntimeException("Could not retrieve file from 1password!");
        }

        $context->getShell()->putFileContents($target_file_name, $content, $context);
    }
}
