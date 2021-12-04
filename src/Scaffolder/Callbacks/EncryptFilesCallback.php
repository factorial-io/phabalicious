<?php

namespace Phabalicious\Scaffolder\Callbacks;

use Defuse\Crypto\Crypto;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Utilities\Utilities;

class EncryptFilesCallback extends CryptoBaseCallback
{


    /**
     * @inheritDoc
     */
    public static function getName(): string
    {
        return 'encrypt_files';
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
            $this->encryptFileTo($context, $file, $arguments[1], $secret);
        }
    }

    protected function encryptFileTo(TaskContextInterface $context, string $input, string $output_dir, $secret)
    {
        $content = $context->getShell()->getFileContents($input, $context);

        $encrypted = Crypto::encryptWithPassword($content, $secret);
        $encrypted = sprintf(
            "\$phabalicious;%s;\n%s",
            Utilities::FALLBACK_VERSION,
            wordwrap($encrypted, 80, "\n", true)
        );

        $target_file_name = $output_dir . '/' . basename($input) . '.enc';
        $context->getConfigurationService()->getLogger()->info(sprintf(
            "%s: Writing encrypted data to %s",
            $this->getName(),
            $target_file_name
        ));

        $context->getShell()->run(sprintf('mkdir -p "%s"', dirname($target_file_name)));

        $context->getShell()->putFileContents(
            $target_file_name,
            $encrypted,
            $context
        );
    }
}
