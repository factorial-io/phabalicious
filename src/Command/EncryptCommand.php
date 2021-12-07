<?php

namespace Phabalicious\Command;

use Phabalicious\Configuration\HostConfig;
use Phabalicious\Method\ScriptMethod;
use Phabalicious\ShellProvider\LocalShellProvider;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class EncryptCommand extends BaseOptionsCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('encrypt')
            ->setDescription('Encrypts a list of files with a password')
            ->addArgument(
                'source',
                InputArgument::REQUIRED,
                'The files to encrypt'
            )
            ->addArgument(
                'target',
                InputArgument::REQUIRED,
                'Where to store the encrypted files'
            )
            ->addOption(
                'password',
                null,
                InputOption::VALUE_OPTIONAL,
                "the password to use to encrypt the files",
                false
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $source = realpath(dirname($input->getArgument('source'))) . '/' . basename($input->getArgument('source'));
        $target = realpath($input->getArgument('target'));
        $script = [
            sprintf(
                'encrypt_files(%s, %s, mysecret)',
                $source,
                $target
            ),
        ];

        $this->configuration->setSetting('secrets', [
            'mysecret' => [
                'question' => 'Please provide a password to use for encryption',
                'hidden' => true,
            ]
        ]);

        if (!empty($input->getOption('password'))) {
            $this
                ->configuration
                ->getPasswordManager()
                ->setSecret('mysecret', $input->getOption('password'));
        }

        $this->createContext($input, $output);

        $context = $this->getContext();
        $context->set(ScriptMethod::SCRIPT_DATA, $script);

        $logger = $this->getConfiguration()->getLogger();
        $shell = new LocalShellProvider($logger);
        $host_config = new HostConfig([
            'shellExecutable' => '/bin/bash',
            'rootFolder' => getcwd(),
        ], $shell, $this->getConfiguration());
        $shell->setup();

        $context->io()->comment('Encrypting files ...');

        $script_method = new ScriptMethod($logger);
        $script_method->runScript($host_config, $context);

        return $context->getResult('exitCode', 0);
    }
}
