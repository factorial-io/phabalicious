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
use Phabalicious\Method\K8sMethod;
use Phabalicious\ShellProvider\CommandResult;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class K8sCommand extends BaseCommand
{
    public function completeArgumentValues($argumentName, CompletionContext $context): array
    {
        if ('k8s' == $argumentName) {
            return K8sMethod::AVAILABLE_SUB_COMMANDS;
        }

        return parent::completeArgumentValues($argumentName, $context);
    }

    protected function configure()
    {
        parent::configure();
        $this
            ->setName('k8s')
            ->setDescription('Run a k8s command')
            ->setHelp('Runs a k8s command against the given host-config');
        $this->addArgument(
            'k8s',
            InputArgument::REQUIRED | InputArgument::IS_ARRAY,
            'The k8s-command to run'
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
        $subcommands = $input->getArgument('k8s');
        if (!is_array($subcommands)) {
            $subcommands = [$subcommands];
        }
        $context->set('command', implode(' ', $subcommands));

        // Allow methods to override the used shellProvider:
        $host_config = $this->getHostConfig();
        $this->getMethods()->runTask('k8s', $host_config, $context);

        /** @var CommandResult $result */
        $result = $context->getResult('commandResult', new CommandResult(0, []));

        return $result->getExitCode();
    }
}
