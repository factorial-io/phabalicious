<?php

namespace Phabalicious\ShellProvider;

use MongoDB\Driver\Command;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Phabalicious\Validation\ValidationService;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

class LocalShellProvider extends BaseShellProvider implements ShellProviderInterface
{

    const RESULT_IDENTIFIER = '##RESULT:';

    /** @var Process */
    private $process;

    /** @var InputStream */
    private $input;


    public function getName(): string
    {
        return 'local';
    }


    public function getDefaultConfig(ConfigurationService $configuration_service, array $host_config): array
    {
        $result = parent::getDefaultConfig($configuration_service, $host_config);
        $result['shellExecutable'] = $configuration_service->getSetting('shellExecutable', '/bin/sh -i');

        return $result;
    }

    public function validateConfig(array $config, ValidationErrorBagInterface $errors)
    {
        parent::validateConfig($config, $errors);

        $validator = new ValidationService($config, $errors, 'host-config');
        $validator->hasKey(
            'shellExecutable',
            'Missing shellExecutable, should point to the executable to run an interactive shell'
        );
    }

    public function setup()
    {
        if ($this->process) {
            return;
        }

        $this->process = new Process([$this->hostConfig['shellExecutable']]);
        $this->process->setTimeout(0);
        $this->input = new InputStream();
        $this->process->setInput($this->input);
        $this->process->start(function ($type, $buffer) {
            if ($type == \Symfony\Component\Process\Process::ERR) {
                $this->logger->error($buffer);
            }
        });
    }

    public function run(string $command, $capture_output = false): CommandResult
    {
        $this->setup();
        $command = sprintf("cd %s && %s", $this->getWorkingDir(), $command);
        $this->logger->debug('Run ' . $command);

        // Send to shell.
        $this->input->write($command . '; echo "' . self::RESULT_IDENTIFIER . '$?"' . PHP_EOL);

        // Get result.
        $output = '';
        while (strpos($output, self::RESULT_IDENTIFIER) === false) {
            $output .= $this->process->getIncrementalOutput();
        }

        $lines = explode(PHP_EOL, $output);
        do {
            $exit_code = array_pop($lines);
        } while (empty($exit_code));

        $exit_code = intval(str_replace(self::RESULT_IDENTIFIER, '', $exit_code), 10);
        foreach ($lines as $line) {
            $this->logger->log($capture_output ? LogLevel::DEBUG : LogLevel::INFO, $line);
        }
        return new CommandResult($exit_code, $lines);
    }
}