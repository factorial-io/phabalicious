<?php

namespace Phabalicious\Scaffolder\Callbacks;

use Defuse\Crypto\Crypto;
use Phabalicious\Method\TaskContextInterface;

class DecryptFilesCallback extends CryptoBaseCallback
{


    /**
     * @inheritDoc
     */
    public static function getName(): string
    {
        return 'decrypt_files';
    }

    /**
     * @inheritDoc
     */
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

        $first_line = strstr($content, "\n", true);
        $content = str_replace("\n", "", strstr($content, "\n", false));

        $decrypted = Crypto::decryptWithPassword($content, $secret);
    }
}
