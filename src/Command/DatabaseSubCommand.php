<?php

namespace Phabalicious\Command;

use Phabalicious\Exception\BlueprintTemplateNotFoundException;
use Phabalicious\Exception\FabfileNotFoundException;
use Phabalicious\Exception\FabfileNotReadableException;
use Phabalicious\Exception\MethodNotFoundException;
use Phabalicious\Exception\MismatchedVersionException;
use Phabalicious\Exception\MissingDockerHostConfigException;
use Phabalicious\Exception\ShellProviderNotFoundException;
use Phabalicious\Exception\TaskNotFoundInMethodException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class DatabaseSubCommand extends BaseCommand implements DatabaseSubCommandInterface
{
    protected function configure()
    {
        parent::configure();
        $info = $this->getSubcommandInfo();
        $this
            ->setName('db:' . $info['subcommand'])
            ->setAliases(['database:' . $info['subcommand']])
            ->setDescription($info['description'])
            ->setHelp($info['help']);

        foreach ($this->getSubcommandArguments() as $name => $description) {
            $this->addArgument($name, true, $description);
        }
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int

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
        $what = strtolower($this->getSubcommandInfo()['subcommand']);
        $context->set('what', $what);

        foreach ($this->getSubcommandArguments() as $name => $desc) {
            $context->set($name, $input->getArgument($name));
        }

        $this->getMethods()
            ->runTask('database', $this->getHostConfig(), $context);

        if ($this->getContext()->getCommandResult() && $this->getContext()->getCommandResult()->failed()) {
            $this->getContext()->getCommandResult()->throwException("Query failed!");
        }

        $return_code = $context->getResult('exitCode', 0);
        if ($return_code === 0) {
            $context->io()
                ->success(sprintf('Database-command `%s` executed successfully!', $what));
        }
        return $return_code;
    }

    public function getSubcommandArguments(): array
    {
        return [];
    }
}
