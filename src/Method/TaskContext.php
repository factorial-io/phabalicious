<?php

namespace Phabalicious\Method;

use Phabalicious\Command\BaseOptionsCommand;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\ShellProvider\CommandResult;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Phabalicious\Utilities\PasswordManagerInterface;
use Phabalicious\Utilities\Utilities;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

class TaskContext implements TaskContextInterface
{
    private $data = [];

    private $result = [];

    private $input;

    private $output;

    private $command;

    private $configurationService;

    private $commandResult;

    private $shell;

    private $io;

    public function __construct(?BaseOptionsCommand $command, InputInterface $input, OutputInterface $output)
    {
        $this->setInput($input);
        $this->setOutput($output);
        if ($command) {
            $this->setCommand($command);
            $this->setConfigurationService($command->getConfiguration());
        }
    }

    public function set(string $key, $value)
    {
        $this->data[$key] = $value;
    }

    public function mergeAndSet(string $key, array $value)
    {
        $stored_value = $this->get($key, []);
        $stored_value = Utilities::mergeData($stored_value, $value);
        $this->set($key, $stored_value);
    }

    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
    }

    public function getOutput(): ?OutputInterface
    {
        return $this->output;
    }

    public function setConfigurationService(?ConfigurationService $service)
    {
        $this->configurationService = $service;
        if ($this->configurationService && $this->configurationService->getPasswordManager()) {
            $this->configurationService->getPasswordManager()->setContext($this);
        }
    }

    public function getConfigurationService(): ?ConfigurationService
    {
        return $this->configurationService;
    }

    public function setCommand(BaseOptionsCommand $command)
    {
        $this->command = $command;
    }

    public function getCommand(): BaseOptionsCommand
    {
        return $this->command;
    }

    public function setInput(InputInterface $input)
    {
        $this->input = $input;
    }

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

    public function getShell(): ?ShellProviderInterface
    {
        return $this->shell;
    }

    public function setShell(ShellProviderInterface $shell)
    {
        $this->shell = $shell;
    }

    public function setResult($key, $value)
    {
        $this->result[$key] = $value;
    }

    public function getResult($key, $default = null): mixed
    {
        return $this->result[$key] ?? $default;
    }

    public function getResults(): array
    {
        return $this->result;
    }

    public function mergeData(TaskContextInterface $context)
    {
        $this->data = Utilities::mergeData($this->data, $context->getData());
    }

    public function mergeResults(TaskContextInterface $context)
    {
        $this->result = Utilities::mergeData($this->result, $context->getResults());
        if ($context->getCommandResult()) {
            $this->setCommandResult($context->getCommandResult());
        }
    }

    public function addResult(string $key, array $rows)
    {
        $result = $this->getResult($key, []);
        $result = array_merge($result, $rows);
        $this->setResult($key, $result);
    }

    public function clearResults()
    {
        $this->result = [];
    }

    public function askQuestion(string $question)
    {
        $question = new Question($question);
        $question_helper = new QuestionHelper();

        return $question_helper->ask($this->input, $this->output, $question);
    }

    public function getPasswordManager(): ?PasswordManagerInterface
    {
        return $this->getConfigurationService()?->getPasswordManager();
    }

    public function io(): SymfonyStyle
    {
        if (!$this->io) {
            $output = $this->getOutput();
            // Use stderr if available.
            if (!$output->isDecorated() && $output instanceof ConsoleOutputInterface) {
                $output = $output->getErrorOutput();
            }
            $this->io = new SymfonyStyle($this->getInput(), $output);
        }

        return $this->io;
    }

    public function setIo(SymfonyStyle $io)
    {
        $this->io = $io;
    }
}
