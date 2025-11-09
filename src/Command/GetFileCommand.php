<?php

namespace Phabalicious\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GetFileCommand extends BaseCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this
            ->setName('get:file')
            ->setDescription('Get a file from a remote instance')
            ->setHelp('
Copies a single file from a remote instance to your local computer.

This command downloads a file from the remote host to your local machine.
The file is copied to the current working directory using the same filename.

Behavior:
- Copies the specified file from the remote host
- Saves it to the current working directory with the same filename
- Shows success message with the target file path
- Returns error if the file cannot be copied

The file path can be absolute or relative to the remote rootFolder.

Arguments:
- <file>: Path to the file on the remote instance to download

Examples:
<info>phab --config=myconfig get:file /path/to/config.yml</info>
<info>phab --config=production get:file settings.php</info>
<info>phab --config=myconfig getFile logs/error.log</info>  # Using alias
            ');
        $this->addArgument(
            'file',
            InputArgument::REQUIRED,
            'The file to copy from the remote instance'
        );

        $this->setAliases(['getFile']);
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
        $file = $input->getArgument('file');

        $context = $this->getContext();
        $context->set('sourceFile', $file);
        $context->set('destFile', getcwd().'/'.basename($file));

        $context->io()->comment('Get file `'.$file.'` from `'.$this->getHostConfig()->getConfigName().'`');

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
