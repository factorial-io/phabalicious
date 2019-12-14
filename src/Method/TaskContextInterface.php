<?php

namespace Phabalicious\Method;

use Phabalicious\Command\BaseCommand;
use Phabalicious\Command\BaseOptionsCommand;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\ShellProvider\CommandResult;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Phabalicious\Utilities\PasswordManagerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

interface TaskContextInterface
{
    public function set(string $key, $data);

    public function mergeAndSet(string $string, array $array);

    public function get(string $key, $default = null);

    public function getData(): array;

    public function setInput(InputInterface $input);

    public function getInput(): InputInterface;

    public function setOutput(OutputInterface $output);

    public function getOutput(): ?OutputInterface;

    public function setCommand(BaseOptionsCommand $command);

    public function getCommand() : BaseOptionsCommand;

    public function setConfigurationService(ConfigurationService $service);

    public function getConfigurationService(): ConfigurationService;

    public function setCommandResult(CommandResult $command_result);

    public function getCommandResult(): ?CommandResult;

    public function setResult($key, $value);

    public function addResult(string $key, array $rows);

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getResult($key, $default = null);

    public function getResults(): array;

    public function clearResults();

    /**
     * @return ShellProviderInterface|null
     */
    public function getShell() : ?ShellProviderInterface;

    public function setShell(ShellProviderInterface $shell);

    public function mergeResults(TaskContextInterface $context);

    public function askQuestion(string $string);

    /**
     * @return PasswordManagerInterface
     */
    public function getPasswordManager();

    /**
     * @return SymfonyStyle
     */
    public function io();
}
