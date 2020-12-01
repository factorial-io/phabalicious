<?php

namespace Phabalicious\Command;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Exception\BlueprintTemplateNotFoundException;
use Phabalicious\Exception\FabfileNotFoundException;
use Phabalicious\Exception\FabfileNotReadableException;
use Phabalicious\Exception\MismatchedVersionException;
use Phabalicious\Exception\MissingDockerHostConfigException;
use Phabalicious\Exception\ShellProviderNotFoundException;
use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\Exception\MissingHostConfigException;
use Phabalicious\ShellCompletion\FishShellCompletionContext;
use Phabalicious\ShellProvider\ShellOptions;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Phabalicious\Utilities\ParallelExecutor;
use Phabalicious\Utilities\Utilities;
use Psr\Log\NullLogger;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

abstract class BaseCommand extends BaseOptionsCommand
{
    /** @var HostConfig */
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
            )
            ->addOption(
                'num-threads',
                null,
                InputOption::VALUE_OPTIONAL,
                'Run variants in num threads',
                4
            )
            ->addOption(
                'variants',
                null,
                InputOption::VALUE_OPTIONAL,
                'Run the command on a given set of blueprints simultanously',
                null
            )
            ->addOption(
                'set',
                's',
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'Set an existing host config value, using key=value',
                []
            )
            ->addOption(
                'force',
                null,
                InputOption::VALUE_OPTIONAL,
                'Don\'t ask for confirmation',
                false
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
        if ($optionName == 'set' && $context instanceof FishShellCompletionContext) {
            $dotted = [];
            if ($host_config = $context->getHostConfig()) {
                Utilities::pushKeysAsDotNotation($host_config->raw(), $dotted, ['host']);

                $docker_config_name = $host_config['docker']['configuration'] ?? false;
                if ($docker_config_name && $docker_config = $context->getDockerConfig($docker_config_name)) {
                    Utilities::pushKeysAsDotNotation($docker_config->raw(), $dotted, ['docker']);
                }
            }
            return $dotted;
        }
        return parent::completeOptionValues($optionName, $context);
    }

    /**
     * {@inheritdoc}
     * @throws MismatchedVersionException
     * @throws FabfileNotFoundException
     * @throws FabfileNotReadableException
     * @throws MissingDockerHostConfigException
     * @throws ShellProviderNotFoundException
     * @throws BlueprintTemplateNotFoundException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

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

            $this->hostConfig->shell()->setOutput($output);

            if ($input->getOption('variants')) {
                $this->handleVariants($input->getOption('variants'), $input, $output);
                return 2;
            }
            if ($input->getOption('set')) {
                $this->handleSetOption($input->getOption('set'));
            }
        } catch (MissingHostConfigException $e) {
            $io->error(sprintf('Could not find host-config named `%s`', $config_name));
            return 1;
        } catch (ValidationFailedException $e) {
            $io->error(sprintf(
                "Could not validate config `%s`\n\n%s",
                $config_name,
                implode("\n", $e->getValidationErrors())
            ));
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

    protected function getDockerConfig() : ?HostConfig
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
     * @param OutputInterface $output
     * @return ShellOptions
     */
    protected function getSuitableShellOptions(OutputInterface $output): ShellOptions
    {
        $options = new ShellOptions();
        $options
            ->setUseTty($output->isDecorated())
            ->setQuiet($output->isQuiet());
        return $options;
    }

    /**
     * @param SymfonyStyle $io
     * @param ShellProviderInterface $shell
     * @param array $command
     * @param ShellOptions|null $options
     * @return Process
     */
    protected function startInteractiveShell(
        SymfonyStyle $io,
        ShellProviderInterface $shell,
        array $command = [],
        ShellOptions $options = null
    ) {
        $fn = function ($type, $buffer) use ($io) {
            if ($type == Process::ERR) {
                $io->error($buffer);
            } else {
                $io->write($buffer);
            }
        };
        if (!$options) {
            $options = new ShellOptions();
        }

        /** @var Process $process */
        if (!empty($command)) {
            $options->setShellExecutableProvided(true);
            $command = $shell->wrapCommandInLoginShell($command);
        }
        $process = $shell->createShellProcess($command, $options);
        if ($options->useTty()) {
            $stdin = fopen('php://stdin', 'r');
            $process->setInput($stdin);
        }
        $process->setTimeout(0);
        $process->setTty($options->useTty());
        $process->start($fn);
        $process->wait($fn);
        if ($process->isTerminated() && !$process->isSuccessful()) {
            $io->error(sprintf(
                'Command %s failed with error %s',
                $process->getCommandLine(),
                $process->getExitCode()
            ));
        }

        return $process;
    }

    /**
     * Handle variants.
     *
     * @param string $variants
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return bool|int
     */
    private function handleVariants($variants, InputInterface $input, OutputInterface $output)
    {
        global $argv;
        $executable = $argv[0];
        if (basename($executable) !== 'phab') {
            $executable = 'bin/phab';
        }
        if (getenv('PHABALICIOUS_EXECUTABLE')) {
            $executable = getenv('PHABALICIOUS_EXECUTABLE');
        }

        $base_path = getcwd();

        $available_variants = $this->configuration->getBlueprints()->getVariants($this->hostConfig['configName']);
        if (!$available_variants) {
            throw new \InvalidArgumentException(sprintf(
                'Could not find variants for `%s` in `blueprints`',
                $this->hostConfig['configName']
            ));
        }

        if ($variants == 'all') {
            $variants = $available_variants;
        } else {
            $variants = explode(',', $variants);
            $not_found = array_filter($variants, function ($v) use ($available_variants) {
                return !in_array($v, $available_variants);
            });

            if (!empty($not_found)) {
                throw new \InvalidArgumentException(sprintf(
                    'Could not find variants `%s` in `blueprints`',
                    implode('`, `', $not_found)
                ));
            }
        }
        if (!empty($variants)) {
            $cmd_lines = [];
            $rows = [];
            foreach ($variants as $v) {
                $cmd = [];
                $cmd[] = $executable;

                foreach ($input->getArguments() as $a) {
                    if (is_array($a)) {
                        $cmd[] = implode(' ', $a);
                    } else {
                        $cmd[] = $a;
                    }
                }
                foreach ($input->getOptions() as $name => $value) {
                    if ($value && !in_array($name, ['verbose', 'variants', 'blueprint', 'fabfile', 'num-threads'])) {
                        if (!is_array($value)) {
                            $value = [$value];
                        }
                        foreach ($value as $vv) {
                            $cmd[] = '--' . $name;
                            if (!in_array($name, ['no-interaction', 'no-ansi'])) {
                                $cmd[] = $vv;
                            }
                        }
                    }
                }
                $cmd[] = '--no-interaction';
                $cmd[] = '--fabfile';
                $cmd[] = Utilities::getRelativePath($base_path, $this->configuration->getFabfileLocation());
                $cmd[] = '--blueprint';
                $cmd[] = $v;

                if ($output->isVeryVerbose()) {
                    $cmd[] = '-vv';
                } elseif ($output->isVerbose()) {
                    $cmd[] = '-v';
                }

                $cmd_lines[] = $cmd;

                $rows[] = [$v, implode(' ', $cmd)];
            }

            $io = new SymfonyStyle($input, $output);
            $io->table(['variant', 'command'], $rows);

            if ($input->getOption('force') !== false || $io->confirm('Do you want to run these commands? ', false)) {
                $io->comment('Running ...');
                $executor = new ParallelExecutor($cmd_lines, $output, $input->getOption('num-threads'));
                return $executor->execute($input, $output);
            }

            return 1;
        }
    }

    private function handleSetOption($option_value)
    {
        $options = is_array($option_value) ? $option_value : explode(" ", $option_value);
        foreach ($options as $option) {
            [$key_combined, $value] = explode("=", $option, 2);
            [$what, $key] = array_pad(explode(".", $key_combined, 2), 2, false);
            if (!in_array($what, ['host', 'dockerHost'])) {
                $what = 'host';
                $key = $key_combined;
            }
            if ($what == 'host') {
                $this->hostConfig->setProperty($key, $value);
            } elseif ($what == 'docker') {
                $docker_config = $this->getDockerConfig();
                if ($docker_config) {
                    $docker_config->setProperty($key, $value);
                } else {
                    throw new \InvalidArgumentException('Can\'t set value for unavailable docker-config');
                }
            } else {
                throw new \InvalidArgumentException(sprintf('Unknown type for set-option: %s', $option));
            }
        }
    }

    /**
     * Prepare arguments, so they dan consumed as cmd arguments again.
     *
     * @param array|string[] $input_arguments
     * @return string
     */
    protected function prepareArguments(array $input_arguments)
    {
        $arguments = array_map(function ($elem) {
            if (strpos($elem, ' ') !== false) {
                return escapeshellarg($elem);
            }
            return $elem;
        }, $input_arguments);
        return implode(' ', $arguments);
    }
}
