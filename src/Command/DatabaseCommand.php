<?php

/** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Command;

use Phabalicious\Exception\BlueprintTemplateNotFoundException;
use Phabalicious\Exception\FabfileNotFoundException;
use Phabalicious\Exception\FabfileNotReadableException;
use Phabalicious\Exception\MethodNotFoundException;
use Phabalicious\Exception\MismatchedVersionException;
use Phabalicious\Exception\MissingDockerHostConfigException;
use Phabalicious\Exception\ShellProviderNotFoundException;
use Phabalicious\Exception\TaskNotFoundInMethodException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DatabaseCommand extends BaseCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('db')
            ->setAliases(['database'])
            ->setDescription('Interact with a database')
            ->setHelp('Run specific commands against the database');
        $this->addArgument(
            'what',
            InputArgument::REQUIRED,
            'The subcommand to execute on the database'
        );
    }

    /**
     * @throws BlueprintTemplateNotFoundException
     * @throws FabfileNotFoundException
     * @throws FabfileNotReadableException
     * @throws MethodNotFoundException
     * @throws MismatchedVersionException
     * @throws MissingDockerHostConfigException
     * @throws ShellProviderNotFoundException
     * @throws TaskNotFoundInMethodException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($result = parent::execute($input, $output)) {
            return $result;
        }

        $context = $this->getContext();
        $what = strtolower($input->getArgument('what'));
        if (!in_array($what, ['drop', 'install'])) {
            throw new \RuntimeException(sprintf('Unsupported database command: `%s`', $what));
        }

        $context->set('what', $what);

        $this->getMethods()->runTask('database', $this->getHostConfig(), $context);

        $return_code = $context->getResult('exitCode', 0);
        if (0 === $return_code) {
            $context->io()->success(sprintf('Database-command `%s` executed successfully!', $what));
        }

        return $return_code;
    }
}
