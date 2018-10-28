<?php

namespace Phabalicious\Method;

use Phabalicious\Command\BaseCommand;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\ShellProvider\CommandResult;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

interface TaskContextInterface
{
    public function set(string $key, $data);

    public function get(string $key, $default = null);

    public function setInput(InputInterface $input);

    public function getInput(): InputInterface;

    public function setOutput(OutputInterface $output);

    public function getOutput(): OutputInterface;

    public function setCommand(BaseCommand $command);

    public function getCommand() : BaseCommand;

    public function setConfigurationService(ConfigurationService $service);

    public function getConfigurationService(): ConfigurationService;

    public function setCommandResult(CommandResult $command_result);

    public function getCommandResult(): ?CommandResult;

    public function setResult($key, $value);

    public function addResult(string $key, array $rows);

    public function getResult($key, $default = null);

    public function getResults(): array;

    /**
     * @return ShellProviderInterface
     */
    public function getShell();

    public function setShell(ShellProviderInterface $shell);

    public function mergeResults(TaskContextInterface $context);



}

