<?php

namespace Phabalicious\Command;

use Phabalicious\Method\TaskContext;
use Phabalicious\Utilities\Utilities;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GetFileCommand extends BaseCommand
{

    protected function configure()
    {
        parent::configure();
        $this
            ->setName('get:file')
            ->setDescription('Get a file from a remote instance')
            ->setHelp('Copies a remote file to your local');
        $this->addArgument(
            'file',
            InputArgument::REQUIRED,
            'The file to copy from the remote instance'
        );

        $this->setAliases(['getFile']);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     * @throws \Phabalicious\Exception\BlueprintTemplateNotFoundException
     * @throws \Phabalicious\Exception\FabfileNotFoundException
     * @throws \Phabalicious\Exception\FabfileNotReadableException
     * @throws \Phabalicious\Exception\MethodNotFoundException
     * @throws \Phabalicious\Exception\MismatchedVersionException
     * @throws \Phabalicious\Exception\MissingDockerHostConfigException
     * @throws \Phabalicious\Exception\ShellProviderNotFoundException
     * @throws \Phabalicious\Exception\TaskNotFoundInMethodException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($result = parent::execute($input, $output)) {
            return $result;
        }
        $file = $input->getArgument('file');


        $context = $this->getContext();
        $context->set('sourceFile', $file);
        $context->set('destFile', getcwd() . '/' . basename($file));

        $context->io()->comment('Get file `' . $file . '` from `' . $this->getHostConfig()->getConfigName(). '`');

        $this->getMethods()->runTask('getFile', $this->getHostConfig(), $context);

        $return_code = $context->getResult('exitCode', 0);
        if (!$return_code) {
            $context->io()->success(sprintf(
                '`%s` copied to `%s`',
                $file,
                $context->getResult('targetFile', 'unknown')
            ));
        }
        return $return_code;
    }
}
