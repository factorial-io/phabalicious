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
            ->setName('put:file')
            ->setDescription('Put a file into a remote instance')
            ->setHelp('Copies a local file to a remote instance');
        $this->addArgument(
            'file',
            InputArgument::REQUIRED,
            'The file to copy to the remote instance'
        );

        $this->addOption(
            'destination',
            'd',
            InputOption::VALUE_OPTIONAL,
            'The target destination to copy the file to, provide a full path including the filename.'
        );

        $this->setAliases(['putFile']);
        $this->setHelp('
Copies a local <info>file</info> to a remote instance specified by the configuration. You can
specify the full path and filename by providing the <info>--destination</info> option,
relative paths are relative to the rootFolder-config of the remote instance.

Per default phab copies the file to the specified <info>rootFolder</info> from the given
configuration and it keeps the filename.

Existing files will be overridden without warning.

Examples:
<info>phab -cconfig put:file foobar.txt</info>
  Will copy foobar.txt to the root folder as described in the config of the
  remote instance

<info>phab -cconfig put:file foobar.txt --destination=/var/www/html/index.html</info>
  Will copy foobar.txt to /var/ww/html/index.html on the remote instance
  described by the given config.
        ');
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

        if (!file_exists($file)) {
            throw new \RuntimeException('Could not find file `' . $file . '`!');
        }

        $context = $this->getContext();
        $context->set('sourceFile', $file);
        $context->set('destinationFile', $input->getOption('destination'));

        $context->io()->comment('Putting file `' . $file . '` to `' . $this->getHostConfig()->getConfigName(). '`');

        $this->getMethods()->runTask('putFile', $this->getHostConfig(), $context);

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
