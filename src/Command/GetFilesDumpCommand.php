<?php

namespace Phabalicious\Command;

use Phabalicious\Exception\EarlyTaskExitException;
use Phabalicious\Method\TaskContext;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class GetFilesDumpCommand extends BaseCommand
{

    protected function configure()
    {
        $this
            ->setName('get:files-dump')
            ->setDescription('Get a current dump of all files')
            ->setHelp('
Creates an archive of files on the remote host and copies it to your local computer.

This command creates a tar archive of the configured files/folders on the
remote host, downloads it to your local machine, and then removes the
temporary archive from the remote server.

Behavior:
- Creates a tar archive of files from the configured filesFolder
- Downloads the archive to the current working directory
- Removes the temporary archive from the remote host
- Displays a list of downloaded archive files
- Can pass custom options to the tar command

The files to include are determined by the filesFolder configuration.

Arguments:
- <options>: Additional options to pass to the tar command (optional, multiple allowed)

Examples:
<info>phab --config=myconfig get:files-dump</info>
<info>phab --config=myconfig get:files-dump --exclude "*.log"</info>
<info>phab --config=myconfig getFilesDump</info>  # Using alias
            ');
        $this->setAliases(['getFilesDump']);

        $this->addArgument(
            'options',
            InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
            'The options to pass to tar',
            []
        );
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
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($result = parent::execute($input, $output)) {
            return $result;
        }

        $context = $this->getContext();
        $context->set('tarOptions', $input->getArgument('options'));

        $this->getMethods()->runTask('getFilesDump', $this->getHostConfig(), $context);
        $to_copy = $context->getResult('files');

        $shell = $context->get('shell', $this->getHostConfig()->shell());
        $files = [];
        foreach ($to_copy as $file) {
            if ($shell->getFile(
                $file,
                getcwd() . '/' . basename($file),
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
