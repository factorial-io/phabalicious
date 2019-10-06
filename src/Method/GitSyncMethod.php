<?php


namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Exception\MethodNotFoundException;
use Phabalicious\Exception\MissingScriptCallbackImplementation;
use Phabalicious\Exception\TaskNotFoundInMethodException;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Phabalicious\Utilities\AppDefaultStages;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Phabalicious\Validation\ValidationService;

class GitSyncMethod extends BuildArtifactsBaseMethod
{

    const PULL_SOURCE_AND_TARGET_REPOSITORY_STAGES = [
        ['stage' => 'installCode'],
        ['stage' => 'installDependencies'],
        ['stage' => 'getChangeLog'],
        ['stage' => 'pullTargetRepository'],
        ['stage' => 'copyFilesToTargetDirectory'],
        ['stage' => 'runDeployScript'],
        ['stage' => 'pushToTargetRepository']
    ];

    const USE_LOCAL_REPOSITORY_STAGES = [
        ['stage' => 'installDependencies'],
        ['stage' => 'getChangeLog'],
        ['stage' => 'pullTargetRepository'],
        ['stage' => 'copyFilesToTargetDirectory'],
        ['stage' => 'runDeployScript'],
        ['stage' => 'pushToTargetRepository']
    ];

    public function getName(): string
    {
        return 'artifacts--git-sync';
    }

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

    public function getDefaultConfig(ConfigurationService $configuration_service, array $host_config): array
    {
        $return = parent::getDefaultConfig($configuration_service, $host_config);
        $return['tmpFolder'] = '/tmp';
        $return['executables'] = [
            'git' => 'git',
        ];
        $return['gitSync'] =[
            'targetBranch' => 'build',
            'useLocalRepository' => false,
        ];

        $return['deployMethod'] = 'git-sync';

        return $return;
    }

    public function validateConfig(array $config, ValidationErrorBagInterface $errors)
    {
        parent::validateConfig($config, $errors);
        if ($config['deployMethod'] !== 'git-sync') {
            $errors->addError('deployMethod', 'deployMethod must be `git-sync`!');
        }
        $service = new ValidationService($config['gitSync'], $errors, 'gitSnyc config');
        $service->hasKey('targetBranch', 'gitSync needs a target branch to push build artifacts to!');
        $service->hasKey('targetRepository', 'gitSync needs a target repository to push build artifacts to!');
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

        if ($use_local_repository = $host_config['gitSync']['useLocalRepository']) {
            $stages = $context->getConfigurationService()->getSetting(
                'appStages.gitSync.useLocalRepository',
                self::USE_LOCAL_REPOSITORY_STAGES
            );
            $install_dir = $host_config['gitRootFolder'];
        } else {
            $stages = $context->getConfigurationService()->getSetting(
                'appStages.gitSync.pullTargetRepository',
                self::PULL_SOURCE_AND_TARGET_REPOSITORY_STAGES
            );
            $install_dir = $host_config['tmpFolder'] . '/' . $host_config['configName'] . '-' . time();
        }

        $target_dir = $host_config['tmpFolder'] . '/' . $host_config['configName'] . '-target-' . time();
        $context->set('installDir', $install_dir);
        $context->set('targetDir', $target_dir);

        $shell = $this->getShell($host_config, $context);

        $this->buildArtifact($host_config, $context, $shell, $install_dir, $stages);

        if (!$use_local_repository) {
            $shell->run(sprintf('rm -rf %s', $install_dir));
        }
        // $shell->run(sprintf('rm -rf %s', $target_dir));
    }

    public function appCreate(HostConfig $host_config, TaskContextInterface $context)
    {
        if (!$current_stage = $context->get('currentStage', false)) {
            throw new \InvalidArgumentException('Missing currentStage on context!');
        }
        $whitelisted_fns = [
            'pullTargetRepository',
            'copyFilesToTargetDirectory',
            'pushToTargetRepository',
            'getChangeLog',
            'runDeployScript',
        ];
        if (in_array($current_stage['stage'], $whitelisted_fns)) {
            $this->{$current_stage['stage']}($host_config, $context);
        }
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
     * @param HostConfig $host_config
     * @param TaskContextInterface $context
     * @throws MethodNotFoundException
     */
    protected function getChangeLog(HostConfig $host_config, TaskContextInterface $context)
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
     * @param HostConfig $host_config
     * @param TaskContextInterface $context
     */
    protected function pullTargetRepository(HostConfig $host_config, TaskContextInterface $context)
    {
        $target_dir = $context->get('targetDir', false);
        $target_branch = $host_config['gitSync']['targetBranch'];
        $target_repository = $host_config['gitSync']['targetRepository'];
            
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
    protected function copyFilesToTargetDirectory(HostConfig $host_config, TaskContextInterface $context)
    {
        /** @var ShellProviderInterface $shell */
        $shell = $context->get('outerShell', $host_config->shell());
        $install_dir = $context->get('installDir', false);
        $target_dir = $context->get('targetDir', false);

        $shell->pushWorkingDir($install_dir);

        $files_to_copy = $host_config['gitSync']['files'] ?? $this->getDirectoryContents($shell, $install_dir);
        $files_to_skip = $context->getConfigurationService()->getSetting('excludeFiles.gitSync', []);

        // Make sure that git-related files are skipped.
        $files_to_skip[] = ".git";
        $files_to_skip[] = ".gitignore";

        foreach ($files_to_copy as $file) {
            if (!in_array($file, $files_to_skip)) {
                $shell->run(sprintf('cp -a %s %s', $file, $target_dir));
            }
        }

        // Delete skipped files in target.
        $shell->cd($target_dir);
        // Keep .git
        $files_to_skip = array_diff($files_to_skip, ['.git']);
        foreach ($files_to_skip as $file) {
            $full_path = $target_dir . '/' . $file;
            $shell->run(sprintf('rm -rf %s', $full_path));
        }
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

        $shell->run('git add -A .');
        $shell->run(sprintf('git commit -m "%s" -m "%s" || true', $message, implode('" -m "', $detailed_message)));
        $shell->run('git push origin');

        $shell->popWorkingDir();
    }


    /**
     * @param ShellProviderInterface $shell
     * @param $install_dir
     * @return array
     */
    private function getDirectoryContents(ShellProviderInterface $shell, $install_dir)
    {
        $contents = $shell->run('ls -1a ' . $install_dir, true);
        return array_filter($contents->getOutput(), function ($elem) {
            return !in_array($elem, ['.', '..']);
        });
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

        $log = $shell->run(sprintf('git log %s..%s --oneline', $last_commit_hash, $current_commit_hash), true);

        $shell->popWorkingDir();
        return $log->getOutput();
    }
}
