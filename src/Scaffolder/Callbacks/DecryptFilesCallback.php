<?php

namespace Phabalicious\Scaffolder\Callbacks;

use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Scaffolder\Callbacks\FileContentsHandler\DecryptFileContentsHandler;

class DecryptFilesCallback extends CryptoBaseCallback
{
    public static function getName(): string
    {
        return 'decrypt_files';
    }

    public static function requires(): string
    {
        return '3.7';
    }

    public function handle(TaskContextInterface $context, ...$arguments)
    {
        $this->validate($context, $arguments);

        $secret = $context->getConfigurationService()->getPasswordManager()->getSecret($arguments[2]);
        if (!$secret) {
            throw new \RuntimeException('Could not find secret `%s`!', $arguments[2]);
        }

        foreach ($this->iterateOverFiles($context, $arguments[0]) as $file) {
            $this->decryptFileTo($context, $file, $arguments[1], $secret);
        }
    }

    protected function decryptFileTo(TaskContextInterface $context, string $input, string $output_dir, $secret)
    {
        $content = $context->getShell()->getFileContents($input, $context);

        $decrypted = DecryptFileContentsHandler::decryptFileContent($content, $secret);

        $target_file_name = $output_dir.'/'.str_replace('.enc', '', basename($input));

        $context->getConfigurationService()->getLogger()->info(sprintf(
            '%s: Writing decrypted data to %s',
            $this->getName(),
            $target_file_name
        ));

        $context->getShell()->run(sprintf('mkdir -p "%s"', dirname($target_file_name)));

        $context->getShell()->putFileContents(
            $target_file_name,
            $decrypted,
            $context
        );
    }
}
