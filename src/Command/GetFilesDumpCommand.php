<?php

namespace Phabalicious\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class GetFilesDumpCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('get:files-dump')
            ->setDescription('Get a current dump of all files')
            ->setHelp('Gets a dump of all files and copies it to your local computer');
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
                getcwd().'/'.basename($file),
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
