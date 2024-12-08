<?php

namespace Phabalicious\Method;

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

    public function mergeAndSet(string $key, array $value);

    public function get(string $key, $default = null);

    public function getData(): array;

    public function setInput(InputInterface $input);

    public function setIo(SymfonyStyle $io);

    public function getInput(): InputInterface;

    public function setOutput(OutputInterface $output);

    public function getOutput(): ?OutputInterface;

    public function setCommand(BaseOptionsCommand $command);

    public function getCommand(): BaseOptionsCommand;

    public function setConfigurationService(ConfigurationService $service);

    public function getConfigurationService(): ConfigurationService;

    public function setCommandResult(CommandResult $command_result);

    public function getCommandResult(): ?CommandResult;

    public function setResult($key, $value);

    public function addResult(string $key, array $rows);

    /**
     * @param string $key
     */
    public function getResult($key, $default = null): mixed;

    public function getResults(): array;

    public function clearResults();

    public function getShell(): ?ShellProviderInterface;

    public function setShell(ShellProviderInterface $shell);

    public function mergeData(TaskContextInterface $context);

    public function mergeResults(TaskContextInterface $context);

    public function askQuestion(string $string);

    public function getPasswordManager(): PasswordManagerInterface;

    public function io(): SymfonyStyle;
}
