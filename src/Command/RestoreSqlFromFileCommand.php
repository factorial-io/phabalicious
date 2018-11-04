<?php

namespace Phabalicious\Command;

use Phabalicious\Method\TaskContext;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RestoreSqlFromFileCommand extends BaseCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('restore:sql-from-file')
            ->setDescription('Restores a database from a sql-file')
            ->setHelp('Restores a database from a given sql-file');
        $this->addArgument(
            'file',
            InputArgument::REQUIRED,
            'The file containing the sql-dump'
        );
        $this->setAliases(['restoreSQLFromFile']);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
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

        $context = new TaskContext($this, $input, $output);
        $file = $input->getArgument('file');
        if (!file_exists($file)) {
            throw new \InvalidArgumentException('Could not find file at `' . $file . '`');
        }

        $host_config = $this->getHostConfig();
        $shell = $host_config->shell();
        $dest = $host_config['tmpFolder'] . '/' .
            $host_config['configName'] . '.' .
            date('YmdHis') . '.' .
            basename($file);

        $shell->putFile($file, $dest, $context);
        $context->set('source', $dest);

        $this->getMethods()->runTask('restoreSqlFromFile', $host_config, $context);

        $shell->run(sprintf('rm %s', $dest));
        $exitCode = $context->getResult('exitCode', 0);
        if ($exitCode == 0) {
            $output->writeln('<info>SQL dump imported successfully</info>');
        }

        return $exitCode;
    }
}
