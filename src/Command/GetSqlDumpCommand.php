<?php

namespace Phabalicious\Command;

use Phabalicious\Exception\EarlyTaskExitException;
use Phabalicious\Method\TaskContext;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class GetSqlDumpCommand extends BaseCommand
{

    protected function configure()
    {
        $this
            ->setName('get:sql-dump')
            ->setDescription('Get a current dump of the database')
            ->setHelp('Gets a dump of the database and copies it to your local computer');
        $this->setAliases(['getSQLDump']);
        parent::configure();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
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
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($result = parent::execute($input, $output)) {
            return $result;
        }

        $context = new TaskContext($this, $input, $output);

        $this->getMethods()->runTask('getSQLDump', $this->getHostConfig(), $context);
        $to_copy = $context->getResult('files');


        $shell = $context->get('shell', $this->getHostConfig()->shell());
        $files = [];
        foreach ($to_copy as $file) {
            if ($shell->getFile(
                $file,
                getcwd(),
                $context
            )) {
                $files[] = basename($file);
            }
            $shell->run(sprintf('rm %s', $file));
        }


        if (count($files) > 0) {
            $io = new SymfonyStyle($input, $output);
            $io->title('Copied dumps to:');
            $io->listing($files);
        }

        return 0;
    }

}