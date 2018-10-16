<?php

namespace Phabalicious\Command;

use Phabalicious\Method\TaskContext;
use Phabalicious\Utilities\Utilities;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PutFileCommand extends BaseCommand
{

    protected function configure()
    {
        parent::configure();
        $this
            ->setName('put-file')
            ->setDescription('Put a file to a remote instance')
            ->setHelp('Copies a local file to a remote instance');
        $this->addArgument(
            'file',
            InputArgument::REQUIRED,
            'The file to copy to the remote instance'
        );

        $this->setAliases(['putFile']);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     * @throws \Phabalicious\Exception\BlueprintTemplateNotFoundException
     * @throws \Phabalicious\Exception\FabfileNotFoundException
     * @throws \Phabalicious\Exception\FabfileNotReadableException
     * @throws \Phabalicious\Exception\MethodNotFoundException
     * @throws \Phabalicious\Exception\MismatchedVersionException
     * @throws \Phabalicious\Exception\MissingDockerHostConfigException
     * @throws \Phabalicious\Exception\ShellProviderNotFoundException
     * @throws \Phabalicious\Exception\TaskNotFoundInMethodException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($result = parent::execute($input, $output)) {
            return $result;
        }
        $file = $input->getArgument('file');

        if (!file_exists($file)) {
            throw new \RuntimeException('Could not find file `' . $file . '`!');
        }

        $context = new TaskContext($this, $input, $output);
        $context->set('sourceFile', $file);

        $output->writeln('<info>Put file `' . $file . '` into `' . $this->getHostConfig()['configName']. '`');

        $this->getMethods()->runTask('putFile', $this->getHostConfig(), $context);

        return $context->getResult('exitCode', 0);
    }
}
