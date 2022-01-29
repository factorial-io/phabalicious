<?php

namespace Phabalicious\ShellProvider;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Configuration\Storage\Node;
use Phabalicious\Exception\SshTunnelFailedException;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\ShellProvider\TunnelHelper\SshTunnelHelper;
use Phabalicious\ShellProvider\TunnelHelper\TunnelSupportInterface;
use Phabalicious\Utilities\EnsureKnownHosts;
use Phabalicious\Utilities\Utilities;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Phabalicious\Validation\ValidationService;
use Symfony\Component\Process\Process;

class SshShellProvider extends LocalShellProvider implements TunnelSupportInterface
{
    const PROVIDER_NAME = 'ssh';

    protected static $cachedSshPorts = [];

    protected static $cachedKnownHostsConfigs = [];

    public function getName(): string
    {
        return self::PROVIDER_NAME;
    }

    public function getDefaultConfig(ConfigurationService $configuration_service, Node $host_config): Node
    {
        $parent = parent::getDefaultConfig($configuration_service, $host_config);
        $result = [];
        $result['shellProviderExecutable'] = '/usr/bin/ssh';
        $result['shellProviderOptions'] = [
            '-o',
            'PasswordAuthentication=no'
        ];
        $result['disableKnownHosts'] = $configuration_service->getSetting('disableKnownHosts', false);
        $result['port'] = 22;

        if (isset($host_config['sshTunnel'])) {
            if (!empty($host_config['port'])) {
                $result['sshTunnel']['localPort'] = $host_config['port'];
            } elseif (!empty($host_config['configName'])) {
                if (!empty(self::$cachedSshPorts[$host_config['configName']])) {
                    $port = self::$cachedSshPorts[$host_config['configName']];
                } else {
                    $port = rand(1024, 49151);
                }
                self::$cachedSshPorts[$host_config['configName']] = $port;
                $result['port'] = $port;
                $result['sshTunnel']['localPort'] = $port;
            }

            if (isset($host_config['docker']['name'])) {
                $result['sshTunnel']['destHostFromDockerContainer'] = $host_config['docker']['name'];
            } elseif (isset($host_config['docker']['service'])) {
                $result['sshTunnel']['destHostFromDockerContainer'] = $host_config['docker']['service'];
            }
        }

        return $parent->merge(new Node($result, $this->getName() . ' defaults'));
    }

    public function validateConfig(Node $config, ValidationErrorBagInterface $errors)
    {
        parent::validateConfig($config, $errors);

        $validation = new ValidationService(
            $config,
            $errors,
            sprintf('host-config: `%s`', $config['configName'] ?? 'unknown config')
        );

        $validation->hasKeys([
            'host' => 'Hostname to connect to',
            'port' => 'The port to connect to',
            'user' => 'Username to use for this connection',
        ]);

        if (!empty($config['sshTunnel'])) {
            $tunnel_validation = new ValidationService(
                $config->get('sshTunnel'),
                $errors,
                sprintf('sshTunnel-config: `%s`', $config['configName'])
            );
            $tunnel_validation->hasKeys([
                'bridgeHost' => 'The hostname of the bridge-host',
                'bridgeUser' => 'The username to use to connect to the bridge-host',
                'bridgePort' => 'The port to use to connect to the bridge-host',
                'destPort' => 'The port of the destination host',
                'localPort' => 'The local port to forward to the destination-host'
            ]);
            if (empty($config['sshTunnel']['destHostFromDockerContainer'])) {
                $tunnel_validation->hasKey('destHost', 'The hostname of the destination host');
            }
        }
        if (isset($config['strictHostKeyChecking'])) {
            $errors->addWarning('strictHostKeyChecking', 'Please use `disableKnownHosts` instead.');
        }
    }

    public function setup()
    {
        if (empty(self::$cachedKnownHostsConfigs[$this->hostConfig->getConfigName()])) {
            EnsureKnownHosts::ensureKnownHosts($this->hostConfig->getConfigurationService(), [
                $this->hostConfig['host'] . ':' . $this->hostConfig['port']
            ]);
            self::$cachedKnownHostsConfigs[$this->hostConfig->getConfigName()] = true;
        }

        parent::setup();
    }

    protected function addCommandOptions(&$command, $override = false)
    {
        if ($override || $this->hostConfig['disableKnownHosts']) {
            $command[] = '-o';
            $command[] = 'StrictHostKeyChecking=no';
            $command[] = '-o';
            $command[] = 'UserKnownHostsFile=/dev/null';
        }
        if (!empty($this->hostConfig['shellProviderOptions'])) {
            $command = array_merge($command, $this->hostConfig['shellProviderOptions']);
        }
    }

    public function getShellCommand(array $program_to_call, ShellOptions $options): array
    {
        $command = [
            $this->hostConfig['shellProviderExecutable'],
            '-A',
            '-p',
            $this->hostConfig['port'],
            ];
        $this->addCommandOptions($command);
        if ($options->useTty()) {
            $command[] = '-t';
        }
        if ($options->isQuiet()) {
            $command[] = '-q';
        }
        $command[] = $this->hostConfig['user'] . '@' . $this->hostConfig['host'];
        if (count($program_to_call)) {
            $command[] = implode(' ', $program_to_call);
        }

        return $command;
    }

    /**
     * @param string $dir
     * @return bool
     * @throws \Exception
     */
    public function exists($dir):bool
    {
        $result = $this->run(sprintf('stat %s > /dev/null 2>&1', $dir), false, false);
        return $result->succeeded();
    }

    public function putFile(string $source, string $dest, TaskContextInterface $context, bool $verbose = false): bool
    {
        $command = [
            '/usr/bin/scp',
            '-P',
            $this->hostConfig['port']
        ];

        $this->addCommandOptions($command);

        $command[] = $source;
        $command[] = $this->hostConfig['user'] . '@' . $this->hostConfig['host'] . ':' . $dest;

        $context->setResult('targetFile', $dest);

        return $this->runProcess($command, $context, false, true);
    }

    public function getFile(string $source, string $dest, TaskContextInterface $context, bool $verbose = false): bool
    {
        $command = [
            '/usr/bin/scp',
            '-P',
            $this->hostConfig['port']
        ];

        $this->addCommandOptions($command);

        $command[] = $this->hostConfig['user'] . '@' . $this->hostConfig['host'] . ':' . $source;
        $command[] = $dest;

        return $this->runProcess($command, $context, false, true);
    }

    public function getSshTunnelCommand(
        string $ip,
        int $port,
        string $public_ip,
        int $public_port,
        $config
    ) {
        $cmd = [
            '/usr/bin/ssh',
            '-A',
            "-L$public_ip:$public_port:$ip:$port",
            '-p',
            $config['port'],
            $config['user'] . '@' . $config['host']
        ];
        $this->addCommandOptions($cmd, true);
        return $cmd;
    }

    public function startRemoteAccess(
        string $ip,
        int $port,
        string $public_ip,
        int $public_port,
        HostConfig $config,
        TaskContextInterface $context
    ) {
        $this->runProcess(
            $this->getSshTunnelCommand($ip, $port, $public_ip, $public_port, $config),
            $context,
            true
        );
    }

    /**
     * @param HostConfig $target_config
     * @param array $prefix
     * @return Process
     * @throws SshTunnelFailedException
     */
    public function createTunnelProcess(HostConfig $target_config, array $prefix = [])
    {
        $tunnel = $target_config['sshTunnel'];
        $bridge = [
            'host' => $tunnel['bridgeHost'],
            'port' => $tunnel['bridgePort'],
            'user' => $tunnel['bridgeUser'],
        ];
        $cmd = $this->getSshTunnelCommand(
            $tunnel['destHost'],
            $tunnel['destPort'],
            $target_config['host'],
            $target_config['port'],
            $bridge
        );

        $cmd[] = '-v';
        $cmd[] = '-N';
        $cmd[] = '-o';
        $cmd[] = 'PasswordAuthentication=no';

        if (count($prefix)) {
            $prefix[] = implode(' ', $cmd);
            $cmd = $prefix;
        }

        $this->logger->info('Starting tunnel with ' . implode(' ', $cmd));

        $process = new Process(
            $cmd
        );
        $process->setTimeout(0);
        $process->start(function ($type, $buffer) {
            $buffer = trim($buffer);
            $this->logger->debug($buffer);
        });

        $result = '';
        while ((strpos($result, 'Entering interactive session') === false) && !$process->isTerminated()) {
            $result .= $process->getIncrementalErrorOutput();
        }
        if ($process->isTerminated() && $process->getExitCode() != 0) {
            throw new SshTunnelFailedException("SSH-Tunnel creation failed with \n" . $result);
        }

        return $process;
    }

    public function copyFileFrom(
        ShellProviderInterface $from_shell,
        string $source_file_name,
        string $target_file_name,
        TaskContextInterface $context,
        bool $verbose = false
    ): bool {
        if ($from_shell->getName() == self::PROVIDER_NAME) {
            $from_host_config = $from_shell->getHostConfig();
            $command = [
                '/usr/bin/scp',
                '-o',
                'PasswordAuthentication=no',
                '-P',
                $from_host_config['port']
            ];

            $this->addCommandOptions($command, true);

            $command[] = $from_host_config['user'] . '@' . $from_host_config['host'] . ':' .$source_file_name;
            $command[] = $target_file_name;

            $cr = $this->run(implode(' ', $command), false, false);
            if ($cr->succeeded()) {
                return true;
            } else {
                $this->logger->warning('Could not copy file via SSH, try fallback');
            }
        }
        return parent::copyFileFrom($from_shell, $source_file_name, $target_file_name, $context, $verbose);
    }

    /**
     * {@inheritdoc}
     */
    public function wrapCommandInLoginShell(array $command)
    {
        return [
            '/bin/bash',
            '--login',
            '-c',
            '\'' . implode(' ', $command) . '\''
        ];
    }

    public static function getTunnelHelperClass(): string
    {
        return SshTunnelHelper::class;
    }

    /**
     * {@inheritdoc}
     */
    public function getRsyncOptions(
        HostConfig $to_host_config,
        HostConfig $from_host_config,
        string $to_path,
        string $from_path
    ) {
        $supported_to_shells = [
            LocalShellProvider::PROVIDER_NAME,
            self::PROVIDER_NAME,
            KubectlShellProvider::PROVIDER_NAME
        ];

        if ((!in_array($to_host_config->shell()->getName(), $supported_to_shells)) ||
            ($from_host_config->shell()->getName() !== self::PROVIDER_NAME)) {
            return false;
        }

        // from ssh to local/ssh is supported.

        $ssh_options =  sprintf(
            'ssh -T -o Compression=no ' .
            '-o PasswordAuthentication=no ' .
            '-o StrictHostKeyChecking=no ' .
            '-o UserKnownHostsFile=/dev/null ' .
            '%s ' .
            '-p %s',
            implode(' ', $from_host_config->get('shellProviderOptions', [])),
            $from_host_config['port']
        );


        return [
            sprintf('-e "%s"', $ssh_options),
            sprintf(' %s@%s:%s/. %s', $from_host_config['user'], $from_host_config['host'], $from_path, $to_path)
        ];
    }
}
