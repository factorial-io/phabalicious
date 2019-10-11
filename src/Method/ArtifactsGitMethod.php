<?php


namespace Phabalicious\Method;

use Phabalicious\Artifact\Actions\ActionFactory;
use Phabalicious\Artifact\Actions\Git\ExcludeAction;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Exception\MethodNotFoundException;
use Phabalicious\Exception\MissingScriptCallbackImplementation;
use Phabalicious\Exception\TaskNotFoundInMethodException;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Phabalicious\Validation\ValidationService;
use Psr\Log\LoggerInterface;

class ArtifactsGitMethod extends ArtifactsBaseMethod
{

    const STAGES = [
         'installCode',
         'installDependencies',
         'getSourceCommitInfo',
         'pullTargetRepository',
         'runActions',
         'runDeployScript',
         'pushToTargetRepository'
    ];

    /**
     * ArtifactsGitMethod constructor.
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        parent::__construct($logger);
        ActionFactory::register($this->getName(), 'exclude', ExcludeAction::class);
    }
    /**
     * @return string
     */
    public function getName(): string
    {
        return 'artifacts--git';
    }

    /**
     * @param string $method_name
     * @return bool
     */
    public function supports(string $method_name): bool
    {
        return $method_name === $this->getName();
    }

    /**
     * Get global settings
     */
    public function getGlobalSettings(): array
    {
        $defaults = parent::getGlobalSettings();
        $defaults['excludeFiles']['gitSync'] = [
            'fabfile.yaml',
            '.fabfile.yaml',
            '.git',
        ];

        return $defaults;
    }

    /**
     * Get default config.
     *
     * @param ConfigurationService $configuration_service
     * @param array $host_config
     * @return array
     */
    public function getDefaultConfig(ConfigurationService $configuration_service, array $host_config): array
    {
        $return = parent::getDefaultConfig($configuration_service, $host_config);
        $return['tmpFolder'] = '/tmp';
        $return['executables'] = [
            'git' => 'git',
        ];
        $return[self::PREFS_KEY] =[
            'targetBranch' => 'build',
            'useLocalRepository' => false,
            'actions' => [
                [
                    'action' => 'copy',
                    'arguments' => [
                        'to' => '.',
                        'from' => '*',
                    ],
                ],
                [
                    'action' => 'delete',
                    'arguments' => [
                        '.fabfile.yaml',
                        'fabfile.yaml',
                        '.projectsCreated'
                        ],
                ],
            ],
        ];

        $return['deployMethod'] = 'git-sync';

        return $return;
    }

    /**
     * Validate config.
     *
     * @param array $config
     * @param ValidationErrorBagInterface $errors
     */
    public function validateConfig(array $config, ValidationErrorBagInterface $errors)
    {
        parent::validateConfig($config, $errors);
        if ($config['deployMethod'] !== 'git-sync') {
            $errors->addError('deployMethod', 'deployMethod must be `git-sync`!');
        }
        $service = new ValidationService($config[self::PREFS_KEY], $errors, 'gitSync config');
        $service->hasKey('branch', 'gitSync needs a target branch to push build artifacts to!');
        $service->hasKey('repository', 'gitSync needs a target repository to push build artifacts to!');
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
        if ($host_config['deployMethod'] !== 'git-sync') {
            return ;
        }

        $stages = $context->getConfigurationService()->getSetting('appStages.artifacts.git', self::STAGES);
        $stages = $this->prepareDirectoriesAndStages($host_config, $context, $stages);

        $shell = $this->getShell($host_config, $context);
        $install_dir = $context->get('installDir');
        $target_dir = $context->get('targetDir');

        $this->buildArtifact($host_config, $context, $shell, $install_dir, $stages);

        $shell->run(sprintf('rm -rf %s', $target_dir));

        if (!$context->get('useLocalRepository')) {
            $shell->run(sprintf('rm -rf %s', $install_dir));
        }
    }

    /**
     * @param HostConfig $host_config
     * @param TaskContextInterface $context
     */
    public function appCreate(HostConfig $host_config, TaskContextInterface $context)
    {
        $this->runStageSteps($host_config, $context, [
           'getSourceCommitInfo',
           'pullTargetRepository',
           'pushToTargetRepository',
        ]);
    }

    /**
     * Return the git method.
     *
     * @param TaskContextInterface $context
     * @return GitMethod
     * @throws MethodNotFoundException
     */
    private function getGitMethod(TaskContextInterface $context)
    {
        return $context->getConfigurationService()->getMethodFactory()->getMethod('git');
    }

    /**
     * Get current tag and commit-hash from source repo.
     *
     * @param HostConfig $host_config
     * @param TaskContextInterface $context
     * @throws MethodNotFoundException
     */
    protected function getSourceCommitInfo(HostConfig $host_config, TaskContextInterface $context)
    {
        if (!$git_method = $this->getGitMethod($context)) {
            return;
        }

        $tag = $git_method->getVersion($host_config, $context);
        $hash = $git_method->getCommitHash($host_config, $context);

        // We need to store the commit-data as result, otherwise the won't persist.
        $context->setResult('commitMessage', sprintf("Commit build artifact for tag %s [%s]", $tag, $hash));
        $context->setResult('commitHash', $hash);
    }

    /**
     * Pull target repository and find last source commit hash in log.
     *
     * @param HostConfig $host_config
     * @param TaskContextInterface $context
     */
    protected function pullTargetRepository(HostConfig $host_config, TaskContextInterface $context)
    {
        $target_dir = $context->get('targetDir', false);
        $target_branch = $host_config[self::PREFS_KEY]['branch'];
        $target_repository = $host_config[self::PREFS_KEY]['repository'];
            
        /** @var ShellProviderInterface $shell */
        $shell = $context->get('outerShell', $host_config->shell());
        $shell->run(sprintf('#!git clone --depth 30 -b %s %s %s', $target_branch, $target_repository, $target_dir));
        $shell->pushWorkingDir($target_dir);
        $log = $shell->run('#!git log --format="%H|%s"', true);
        $found = false;
        foreach ($log->getOutput() as $line) {
            list(, $subject) = explode('|', $line);
            if (preg_match('/\[[0-9a-f]{5,40}\]/', $subject, $result)) {
                $found = substr($result[0], 1, strlen($result[0]) - 2);
                break;
            }
        }
        $context->setResult('lastArtifactCommitHash', $found);
        $shell->popWorkingDir();
    }

    /**
     * @param HostConfig $host_config
     * @param TaskContextInterface $context
     */
    protected function pushToTargetRepository(HostConfig $host_config, TaskContextInterface $context)
    {
        $shell = $context->get('outerShell', $host_config->shell());
        $target_dir = $context->get('targetDir', false);
        $message = $context->getResult('commitMessage', 'Commit build artifact');
        $detailed_message = $context->getResult('detailedCommitMessage', '');

        if ($last_commit_hash = $context->getResult('lastArtifactCommitHash')) {
            $current_commit_hash = $context->getResult('commitHash', 'HEAD');
            $detailed_message = $this->getSourceGitLog($shell, $context, $last_commit_hash, $current_commit_hash);
        }

        /** @var ShellProviderInterface $shell */
        $shell->pushWorkingDir($target_dir);

        $shell->run('#!git add -A .');
        $shell->run(sprintf('#!git commit -m "%s" -m "%s" || true', $message, implode('" -m "', $detailed_message)));
        $shell->run('#!git push origin');

        $shell->popWorkingDir();
    }




    /**
     * @param ShellProviderInterface $shell
     * @param TaskContextInterface $context
     * @param $last_commit_hash
     * @param $current_commit_hash
     * @return array
     */
    private function getSourceGitLog(
        ShellProviderInterface $shell,
        TaskContextInterface $context,
        $last_commit_hash,
        $current_commit_hash
    ) {
        $install_dir = $context->get('installDir', false);
        $shell->pushWorkingDir($install_dir);

        $log = $shell->run(sprintf('#!git log %s..%s --oneline', $last_commit_hash, $current_commit_hash), true);

        $shell->popWorkingDir();
        return $log->getOutput();
    }
}
