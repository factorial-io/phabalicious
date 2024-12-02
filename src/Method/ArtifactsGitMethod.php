<?php


namespace Phabalicious\Method;

use Phabalicious\Artifact\Actions\ActionFactory;
use Phabalicious\Artifact\Actions\Git\ExcludeAction;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Configuration\Storage\Node;
use Phabalicious\Exception\MethodNotFoundException;
use Phabalicious\Exception\MissingScriptCallbackImplementation;
use Phabalicious\Exception\TaskNotFoundInMethodException;
use Phabalicious\ShellProvider\RunOptions;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Phabalicious\Utilities\Utilities;
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
    public function getGlobalSettings(ConfigurationService $configuration): Node
    {
        $parent = parent::getGlobalSettings($configuration);
        $defaults = [];
        $defaults['excludeFiles']['gitSync'] = [
            'fabfile.yaml',
            '.fabfile.yaml',
            '.git',
        ];

        return $parent->merge(new Node($defaults, $this->getName() . ' global settings'));
    }

    /**
     * Get default config.
     *
     * @param ConfigurationService $configuration_service
     * @param \Phabalicious\Configuration\Storage\Node $host_config
     *
     * @return \Phabalicious\Configuration\Storage\Node
     */
    public function getDefaultConfig(ConfigurationService $configuration_service, Node $host_config): \Phabalicious\Configuration\Storage\Node
    {
        $parent = parent::getDefaultConfig($configuration_service, $host_config);
        $return = [];
        $return['tmpFolder'] = '/tmp';
        $return['executables'] = [
            'git' => 'git',
            'find' => 'find',
        ];
        $return[self::PREFS_KEY] = [
            'branch' => false,
            'useLocalRepository' => false,
            'gitOptions' => [
                'clone' => [
                    '--depth 30'
                ]
            ]
        ];

        $return['deployMethod'] = 'git-sync';

        return $parent->merge(new Node($return, $this->getName() . ' method defaults'));
    }

    /**
     * Validate config.
     *
     * @param \Phabalicious\Configuration\ConfigurationService $configuration_service
     * @param \Phabalicious\Configuration\Storage\Node $config
     * @param ValidationErrorBagInterface $errors
     */
    public function validateConfig(
        ConfigurationService $configuration_service,
        Node $config,
        ValidationErrorBagInterface $errors
    ) {

        parent::validateConfig($configuration_service, $config, $errors);

        if ($config['deployMethod'] !== 'git-sync') {
            $errors->addError('deployMethod', 'deployMethod must be `git-sync`!');
        }
        $service = new ValidationService($config[self::PREFS_KEY], $errors, 'artifacts--git config');
        $service->hasKey('repository', 'artifacts--git needs a target repository to push build artifacts to!');
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

        $stages = $this->prepareDirectoriesAndStages($host_config, $context, $stages, false);

        $this->buildArtifact($host_config, $context, $stages);

        $this->cleanupDirectories($host_config, $context);

        $context->setResult('runNextTasks', []);
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
    private function getGitMethod(TaskContextInterface $context): GitMethod
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

        $version = $git_method->getVersion($host_config, $context);
        $tag = $git_method->getTag($host_config, $context);
        $hash = $git_method->getCommitHash($host_config, $context);

        // We need to store the commit-data as result, otherwise the won't persist.
        $context->setResult('commitMessage', sprintf("Commit build artifact for version %s [%s]", $version, $hash));
        $context->setResult('commitHash', $hash);
        $context->setResult('commitTag', $tag);
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
        if (!$target_branch) {
            $target_branch = $host_config['branch'];
        }
        $target_repository = $host_config[self::PREFS_KEY]['repository'];

        /** @var ShellProviderInterface $shell */
        $shell = $context->get('outerShell', $host_config->shell());
        $branch_exists = $shell->run(
            sprintf('#!git ls-remote -h --exit-code %s %s', $target_repository, $target_branch),
            true
        )->succeeded();
        $branch_to_clone = $branch_exists ? $target_branch : $host_config[self::PREFS_KEY]['baseBranch'] ?? 'master';
        $clone_options = $host_config[self::PREFS_KEY]['gitOptions']['clone'] ?? [];
        $shell->run(sprintf(
            '#!git clone %s -b %s %s %s',
            implode(' ', $clone_options),
            $branch_to_clone,
            $target_repository,
            $target_dir
        ));
        $shell->pushWorkingDir($target_dir);

        if (!$branch_exists) {
            // Create newly branch and push it back to remote.
            $shell->run(sprintf('#!git checkout -b %s', $target_branch));
            $shell->run(sprintf('#!git push --set-upstream origin %s', $target_branch));
        }

        $log = $shell->run('#!git log --format="%H|%s"', true);
        $found = false;
        $last_successful_deployment_hash = false;
        $new_commits_since_last_deployment = [];
        foreach ($log->getOutput() as $line) {
            [$commit_hash, $subject] = explode('|', $line);
            if (preg_match('/\[[0-9a-f]{5,40}\]/', $subject, $result)) {
                $found = substr($result[0], 1, strlen($result[0]) - 2);
                $last_successful_deployment_hash = $commit_hash;
                break;
            } else {
                $new_commits_since_last_deployment[] = [
                    "hash" => $commit_hash,
                    "message" => $subject,
                ];
            }
        }
        if (!empty($new_commits_since_last_deployment)) {
            $context->io()->warning("Found new commits on target repository since last artifact deployment");
            $context->io()->table([ "hash" => "Hash", "message" => "Message"], $new_commits_since_last_deployment);

            if ($last_successful_deployment_hash) {
                $affected_files = $shell->run(sprintf(
                    "#!git diff %s..%s --name-only",
                    $last_successful_deployment_hash,
                    $new_commits_since_last_deployment[0]['hash']
                ), true);
                $context->io()->note("Changed files:");
                $context->io()->listing($affected_files->getOutput());
            }

            $forced = (getenv("PHABALICIOUS_FORCE_GIT_ARTIFACT_DEPLOYMENT") ?: false) == "1";
            $forced = $forced || Utilities::hasBoolOptionSet($context->getInput(), 'force');

            if (!$forced &&
                !$context->io()->confirm("Are you sure, you want to continue?", false)
            ) {
                throw new \RuntimeException("Deployment aborted because of user-action!");
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
        $detailed_messages = $context->getResult('detailedCommitMessage', '');

        if ($last_commit_hash = $context->getResult('lastArtifactCommitHash')) {
            $current_commit_hash = $context->getResult('commitHash', 'HEAD');
            $detailed_messages = $this->getSourceGitLog($shell, $context, $last_commit_hash, $current_commit_hash);
        }

        /** @var ShellProviderInterface $shell */
        $shell->pushWorkingDir($target_dir);

        // Delete all .git-subdirectories.
        $shell->run('#!find . -name .git -not -path "./.git" -type d -exec rm -rf {} +');

        $shell->run('#!git add -A .');
        $formatted_message = $message;
        // Add two new lines to the end of the short message for detailed messages.
        if (!empty($detailed_messages)) {
            if (count($detailed_messages) > 40) {
                $detailed_messages = array_slice($detailed_messages, 0, 40);
            }
            $formatted_message .= "\n\n  * " . implode("\n  * ", $detailed_messages);
        }

        $shell->run(sprintf('#!git commit --allow-empty -n -m "%s"', addslashes($formatted_message)));

        if ($tag = $context->getResult('commitTag')) {
            $shell->run(sprintf('#!git push origin :refs/tags/%s || true', $tag));
            $shell->run(sprintf('#!git tag --delete %s || true', $tag));
            $shell->run(sprintf('#!git tag %s', $tag));
        }
        $shell->run('#!git push origin');
        $shell->run('#!git push --tags origin');

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

        $log = $shell->run(sprintf('#!git log %s..%s --oneline', $last_commit_hash, $current_commit_hash), RunOptions::CAPTURE_AND_HIDE_OUTPUT);

        $shell->popWorkingDir();
        return $log->getOutput();
    }
}
