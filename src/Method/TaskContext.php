<?php

namespace Phabalicious\Method;

use Phabalicious\Command\BaseCommand;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\ShellProvider\CommandResult;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TaskContext implements TaskContextInterface
{
    private $data = [];

    private $input;

    private $output;

    private $command;

    private $configurationService;

    private $commandResult;

    private $shell;

    public function __construct(BaseCommand $command, InputInterface $input, OutputInterface $output)
    {
        $this->setInput($input);
        $this->setOutput($output);
        $this->setCommand($command);
        if ($command->getConfiguration()) {
            $this->setConfigurationService($command->getConfiguration());
        }
    }

    public function set(string $key, $value)
    {
        $this->data[$key] = $value;
    }

    public function get(string $key, $default = null)
    {
         return isset($this->data[$key]) ? $this->data[$key] : $default;
    }

    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
    }

    public function getOutput(): OutputInterface
    {
        return $this->output;
    }

    public function setConfigurationService(ConfigurationService $service)
    {
        $this->configurationService = $service;
    }

    public function getConfigurationService(): ConfigurationService
    {
        return $this->configurationService;
    }

    public function setCommand(BaseCommand $command)
    {
        $this->command = $command;
    }

    public function getCommand(): BaseCommand
    {
        return $this->command;
    }

    public function setInput(InputInterface $input)
    {
        $this->input = $input;
    }

    /**
     * @return InputInterface
     */
    public function getInput(): InputInterface
    {
        return $this->input;
    }

    public function setCommandResult(CommandResult $command_result)
    {
        $this->commandResult = $command_result;
    }

    public function getCommandResult(): ?CommandResult
    {
        return $this->commandResult;
    }

    public function getShell()
    {
        return $this->shell;
    }

    public function setShell(ShellProviderInterface $shell)
    {
        $this->shell = $shell;
    }
}