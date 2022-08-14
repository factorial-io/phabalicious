<?php

namespace Phabalicious\Method;

use Phabalicious\Artifact\Actions\ActionFactory;
use Phabalicious\Artifact\Actions\Ftp\ExcludeAction;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Configuration\Storage\Node;
use Phabalicious\Exception\MethodNotFoundException;
use Phabalicious\Exception\MissingScriptCallbackImplementation;
use Phabalicious\Exception\TaskNotFoundInMethodException;
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

    const PASSWORD_KEY     = 'artifact.password';
    const USER_KEY         = 'artifact.user';
    const HOST_KEY         = 'artifact.host';
    const PORT_KEY         = 'artifact.port';
    const ROOT_FOLDER_KEY  = 'artifact.rootFolder';
    const LFTP_OPTIONS_KEY = 'artifact.lftpOptions';

    public function __construct(LoggerInterface $logger)
    {
        parent::__construct($logger);
        ActionFactory::register($this->getName(), 'exclude', ExcludeAction::class);
    }

    public function getName(): string
    {
        return 'artifacts--ftp';
    }

    public function supports(string $method_name): bool
    {
        return in_array($method_name, array('ftp-sync', $this->getName()));
    }

    public function getDefaultConfig(ConfigurationService $configuration_service, Node $host_config): Node
    {
        $parent = parent::getDefaultConfig($configuration_service, $host_config);
        $return['tmpFolder'] = '/tmp';
        $return['executables'] = [
            'lftp' => 'lftp',
        ];

        $return['deployMethod'] = $this->getName();
        $return['artifact'] = [
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

        return $parent->merge(new Node($return, $this->getName() . ' method defaults'));
    }

    /**
     * @param \Phabalicious\Configuration\Storage\Node $config
     * @param ValidationErrorBagInterface $errors
     */
    public function validateConfig(Node $config, ValidationErrorBagInterface $errors)
    {
        parent::validateConfig($config, $errors);

        if (in_array('drush', $config['needs'])) {
            $errors->addError(
                'needs',
                sprintf('The method `%s` is incompatible with the `drush`-method!', $this->getName())
            );
        }
        if ($config['deployMethod'] !== $this->getName()) {
            $errors->addError('deployMethod', sprintf('deployMethod must be `%s`!', $this->getName()));
        }
        if (in_array('ftp-sync', $config['needs'])) {
            $errors->addWarning('needs', sprintf('`ftp-sync` is deprecated, please use `%s`', $this->getName()));
        }
        if (isset($config['ftp'])) {
            $errors->addError('ftp', '`ftp` is deprecated, please use `artifact` instead!');
        }

        $service = new ValidationService($config, $errors, sprintf(
            'host-config.%s.artifact',
            $config['configName'],
        ));
        $service->hasKeys([
            self::USER_KEY => 'the ftp user-name',
            self::HOST_KEY => 'the ftp host to connect to',
            self::PORT_KEY => 'the port to connect to',
            self::ROOT_FOLDER_KEY => 'the rootfolder of your app on the remote file-system',
        ]);
        if (empty($config->getProperty(self::PASSWORD_KEY))) {
            $errors->addWarning(
                self::PASSWORD_KEY,
                'Support for plain passwords is deprecated and will ' .
                'be removed in a future version of phab. Please use the secret-system instead!'
            );
        }
        $service->checkForValidFolderName(self::ROOT_FOLDER_KEY);
    }

    /**
     * @param HostConfig $host_config
     * @param TaskContextInterface $context
     *
     * @throws \Phabalicious\Exception\FailedShellCommandException
     * @throws \Phabalicious\Exception\MethodNotFoundException
     * @throws \Phabalicious\Exception\MissingScriptCallbackImplementation
     * @throws \Phabalicious\Exception\TaskNotFoundInMethodException
     */
    public function deploy(HostConfig $host_config, TaskContextInterface $context)
    {
        if ($host_config['deployMethod'] !== $this->getName()) {
            return;
        }

        if (empty($host_config->getProperty(self::PASSWORD_KEY))) {
            $pw = $context->getPasswordManager();
            $password = $pw->getPasswordFor($pw->getKeyFromLogin(
                $host_config->getProperty(self::HOST_KEY),
                $host_config->getProperty(self::PORT_KEY),
                $host_config->getProperty(self::USER_KEY),
            ));
            $host_config->setProperty(self::PASSWORD_KEY, $password);
        }

        $stages = $context->getConfigurationService()->getSetting('appStages.artifacts.ftp', self::STAGES);
        $stages = $this->prepareDirectoriesAndStages($host_config, $context, $stages, true);

        $this->buildArtifact($host_config, $context, $stages);

        $this->cleanupDirectories($host_config, $context);


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
        $options = implode(' ', $host_config->getProperty(self::LFTP_OPTIONS_KEY));
        if (count($exclude)) {
            $options .= ' --exclude ' . implode(' --exclude ', $exclude);
        }

        // Now we can sync the files via FTP.
        $command_file = $host_config['tmpFolder'] . '/lftp_commands_' . time() . '.x';
        $shell->run(sprintf('touch %s', $command_file));
        $shell->run(sprintf(
            "echo 'open -u %s,%s -p%s %s' >> %s",
            $host_config->getProperty(self::USER_KEY),
            $host_config->getProperty(self::PASSWORD_KEY),
            $host_config->getProperty(self::PORT_KEY),
            $host_config->getProperty(self::HOST_KEY),
            $command_file
        ));
        $shell->run(sprintf(
            'echo "mirror %s -c -e -R  %s %s" >> %s',
            $options,
            $target_dir,
            $host_config->getProperty(self::ROOT_FOLDER_KEY),
            $command_file
        ));

        $shell->run(sprintf('echo "exit" >> %s', $command_file));

        $shell->run(sprintf('#!lftp -f %s', $command_file));

        // Cleanup.
        $shell->run(sprintf('rm %s', $command_file));
    }
}
