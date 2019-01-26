<?php

namespace Phabalicious\Command;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Exception\BlueprintTemplateNotFoundException;
use Phabalicious\Exception\FabfileNotFoundException;
use Phabalicious\Exception\FabfileNotReadableException;
use Phabalicious\Exception\MismatchedVersionException;
use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\Exception\MissingHostConfigException;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Psr\Log\NullLogger;
use Stecman\Component\Symfony\Console\BashCompletion\Completion\CompletionAwareInterface;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

abstract class BaseCommand extends BaseOptionsCommand
{
    private $hostConfig;

    private $dockerConfig;


    protected function configure()
    {
        $default_conf = getenv('PHABALICIOUS_DEFAULT_CONFIG');
        if (empty($default_conf)) {
            $default_conf = null;
        }
        $this
            ->addOption(
                'config',
                'c',
                InputOption::VALUE_REQUIRED,
                'Which host-config should be worked on',
                $default_conf
            )
            ->addOption(
                'blueprint',
                null,
                InputOption::VALUE_OPTIONAL,
                'Which blueprint to use',
                null
            );

        parent::configure();
    }

    public function completeOptionValues($optionName, CompletionContext $context)
    {
        if ($optionName == 'config') {
            $config = new ConfigurationService($this->getApplication(), new NullLogger());
            $config->setOffline(true);
            try {
                $config->readConfiguration(getcwd());
            } catch (\Exception $e) {
                return [];
            }
            return array_keys($config->getAllHostConfigs());
        }
        return parent::completeOptionValues($optionName, $context);
    }

    /**
     * {@inheritdoc}
     * @throws \Phabalicious\Exception\MismatchedVersionException
     * @throws \Phabalicious\Exception\FabfileNotFoundException
     * @throws \Phabalicious\Exception\FabfileNotReadableException
     * @throws \Phabalicious\Exception\MissingDockerHostConfigException
     * @throws \Phabalicious\Exception\ShellProviderNotFoundException
     * @throws \Phabalicious\Exception\BlueprintTemplateNotFoundException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->checkAllRequiredOptionsAreNotEmpty($input);

        $config_name = '' . $input->getOption('config');

        try {
            $this->readConfiguration($input);

            if ($input->hasOption('blueprint') && $blueprint = $input->getOption('blueprint')) {
                $this->hostConfig = $this->getConfiguration()->getHostConfigFromBlueprint(
                    $config_name,
                    $blueprint
                );
            } else {
                $this->hostConfig = $this->getConfiguration()->getHostConfig($config_name);
            }

            if (!empty($this->hostConfig['docker']['configuration'])) {
                $docker_config_name = $this->hostConfig['docker']['configuration'];
                $this->dockerConfig = $this->getConfiguration()->getDockerConfig($docker_config_name);
            }

            if ($this->hostConfig->shell()) {
                $this->hostConfig->shell()->setOutput($output);
            }
        } catch (MissingHostConfigException $e) {
            $output->writeln('<error>Could not find host-config named `' . $config_name . '`</error>');
            return 1;
        } catch (ValidationFailedException $e) {
            $output->writeln('<error>Could not validate config `' . $config_name . '`</error>');
            foreach ($e->getValidationErrors() as $error_msg) {
                $output->writeln('<error>' . $error_msg . '</error>');
            }
            return 1;
        }

        return 0;
    }





    /**
     * Get host config.
     *
     * @return HostConfig
     */
    protected function getHostConfig()
    {
        return $this->hostConfig;
    }

    protected function getDockerConfig()
    {
        return $this->dockerConfig;
    }

    public function runCommand(string $command, array $args, InputInterface $original_input, OutputInterface $output)
    {
        $cmd = $this->getApplication()->find($command);

        $args['command'] = $command;

        foreach ($original_input->getOptions() as $key => $value) {
            // Skip options not available for command.
            if (!$cmd->getDefinition()->hasOption($key)) {
                continue;
            }

            $option_key = '--' . $key;
            if (empty($args[$option_key])) {
                $args[$option_key] = $value;
            }
        };
        $input = new ArrayInput($args);
        return $cmd->run($input, $output);
    }

    /**
     * @param ShellProviderInterface $shell
     * @param array $command
     * @return Process
     */
    protected function startInteractiveShell(ShellProviderInterface $shell, array $command = [])
    {
        /** @var Process $process */
        if (!empty($command)) {
            $command = [
                'bash',
                '--login',
                '-c',
                '\'' . implode(' ', $command) .'\'',
            ];
        }
        $process = $shell->createShellProcess($command, ['tty' => true]);
        $stdin = fopen('php://stdin', 'r');
        $process->setInput($stdin);
        $process->setTimeout(0);
        $process->setTty(true);
        $process->start();
        $process->wait(function ($type, $buffer) {
            if ($type == Process::ERR) {
                fwrite(STDERR, $buffer);
            } else {
                fwrite(STDOUT, $buffer);
            }
        });

        return $process;
    }
}
