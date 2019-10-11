<?php

namespace Phabalicious\Method;

use Phabalicious\Artifact\Actions\ActionFactory;
use Phabalicious\Artifact\Actions\Ftp\ExcludeAction;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Exception\MethodNotFoundException;
use Phabalicious\Exception\MissingScriptCallbackImplementation;
use Phabalicious\Exception\TaskNotFoundInMethodException;
use Phabalicious\Utilities\Utilities;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Phabalicious\Validation\ValidationService;
use Psr\Log\LoggerInterface;

class ArtifactsFtpMethod extends ArtifactsBaseMethod implements MethodInterface
{

    const STAGES = [
        'installCode',
        'installDependencies',
        'runActions',
        'runDeployScript',
        'syncToFtp'
    ];

    public function __construct(LoggerInterface $logger)
    {
        parent::__construct($logger);
        ActionFactory::register($this->getName(), 'exclude', ExcludeAction::class );
    }

    public function getName(): string
    {
        return 'artifacts--ftp';
    }

    public function supports(string $method_name): bool
    {
        return in_array($method_name, array('ftp-sync', $this->getName()));
    }

    public function getDefaultConfig(ConfigurationService $configuration_service, array $host_config): array
    {
        $return = parent::getDefaultConfig($configuration_service, $host_config);
        $return['tmpFolder'] = '/tmp';
        $return['executables'] = [
            'lftp' => 'lftp',
        ];

        $return['deployMethod'] = $this->getName();
        $return[self::PREFS_KEY] = [
            'useLocalRepository' => false,
            'port' => 21,
            'lftpOptions' => [
                '--verbose=1',
                '--no-perms',
                '--no-symlinks',
                '-P 20',
            ],
            'actions' => [
                [
                    'action' => 'copy',
                    'arguments' => [
                        'from' => '*',
                        'to' => '.'
                    ],
                ],
                [
                    'action' => 'exclude',
                    'arguments' => [
                        '.git/',
                        'node_modules/',
                        '.fabfile.yaml',
                        '.projectCreated.yaml',
                        'fabfile.yaml'
                    ],
                ]
            ],
        ];

        return $return;
    }

    /**
     * @param array $config
     * @param ValidationErrorBagInterface $errors
     */
    public function validateConfig(array $config, ValidationErrorBagInterface $errors)
    {
        parent::validateConfig($config, $errors);

        if (in_array('drush', $config['needs'])) {
            $errors->addError('needs', sprintf('The method `%s` is incompatible with the `drush`-method!', $this->getName()));
        }
        if ($config['deployMethod'] !== $this->getName()) {
            $errors->addError('deployMethod', sprintf('deployMethod must be `%s`!', $this->getName()));
        }
        if (in_array('ftp-sync', $config['needs'])) {
            $errors->addWarning('needs', sprintf('`ftp-sync` is deprecated, please use `%s`', $this->getName()));
        }
        if (isset($config['ftp'])) {
            $errors->addError('ftp', sprintf('`ftp` is deprecated, please use `%s` instead!', self::PREFS_KEY));
        }

        if (!empty($config[self::PREFS_KEY])) {
            $service = new ValidationService($config[self::PREFS_KEY], $errors, sprintf(
                'host-config.%s.%s', $config['configName'], self::PREFS_KEY));
            $service->hasKeys([
                'user' => 'the ftp user-name',
                'host' => 'the ftp host to connect to',
                'port' => 'the port to connect to',
                'rootFolder' => 'the rootfolder of your app on the remote file-system',
            ]);
            $service->checkForValidFolderName('rootFolder');
        }
    }

    /**
     * @param HostConfig $host_config
     * @param TaskContextInterface $context
     * @throws MethodNotFoundException
     * @throws MissingScriptCallbackImplementation
     * @throws TaskNotFoundInMethodException
     */
    public function deploy(HostConfig $host_config, TaskContextInterface $context)
    {
        if ($host_config['deployMethod'] !== $this->getName()) {
            return;
        }

        if (empty($host_config[self::PREFS_KEY]['password'])) {
            $ftp = $host_config[self::PREFS_KEY];
            $ftp['password'] = $context->getPasswordManager()->getPasswordFor($ftp['host'], $ftp['port'], $ftp['user']);
            $host_config[self::PREFS_KEY] = $ftp;
        }

        $stages = $context->getConfigurationService()->getSetting( 'appStages.artifacts.ftp', self::STAGES );
        $stages = $this->prepareDirectoriesAndStages($host_config, $context, $stages);

        $shell = $this->getShell($host_config, $context);
        $install_dir = $context->get('installDir');
        $target_dir = $context->get('targetDir');

        $shell->run(sprintf('mkdir -p %s', $target_dir));

        $this->buildArtifact($host_config, $context, $shell, $install_dir, $stages);

        $shell->run(sprintf('rm -rf %s', $install_dir));
        $shell->run(sprintf('rm -rf %s', $target_dir));

        // Do not run any next tasks.
        $context->setResult('runNextTasks', []);
    }

    /**
     * @param HostConfig $host_config
     * @param TaskContextInterface $context
     */
    public function appCreate(HostConfig $host_config, TaskContextInterface $context)
    {
        $this->runStageSteps($host_config, $context, [
            'syncToFtp'
        ]);
    }

    /**
     * @param HostConfig $host_config
     * @param TaskContextInterface $context
     */
    protected function syncToFtp(HostConfig $host_config, TaskContextInterface $context)
    {
        $shell = $this->getShell($host_config, $context);
        $target_dir = $context->get('targetDir', false);
        $exclude = $context->getResult(ExcludeAction::FTP_SYNC_EXCLUDES, []);
        $options = implode(' ', $host_config[self::PREFS_KEY]['lftpOptions']);
        if (count($exclude)) {
            $options .= ' --exclude ' . implode(' --exclude ', $exclude);
        }

        // Now we can sync the files via FTP.
        $command_file = $host_config['tmpFolder'] . '/lftp_commands_' . time() . '.x';
        $shell->run(sprintf('touch %s', $command_file));
        $shell->run(sprintf(
            "echo 'open -u %s,%s -p%s %s' >> %s",
            $host_config[self::PREFS_KEY]['user'],
            $host_config[self::PREFS_KEY]['password'],
            $host_config[self::PREFS_KEY]['port'],
            $host_config[self::PREFS_KEY]['host'],
            $command_file
        ));
        $shell->run(sprintf(
            'echo "mirror %s -c -e -R  %s %s" >> %s',
            $options,
            $target_dir,
            $host_config[self::PREFS_KEY]['rootFolder'],
            $command_file
        ));

        $shell->run(sprintf('echo "exit" >> %s', $command_file));

        $shell->run(sprintf('#!lftp -f %s', $command_file));

        // Cleanup.
        $shell->run(sprintf('rm %s', $command_file));
    }
}
