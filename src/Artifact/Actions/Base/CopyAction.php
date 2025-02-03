<?php

namespace Phabalicious\Artifact\Actions\Base;

use Phabalicious\Artifact\Actions\ActionBase;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\ShellProvider\RunOptions;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Phabalicious\Validation\ValidationService;

class CopyAction extends ActionBase
{
    protected function validateArgumentsConfig(array $action_arguments, ValidationService $validation)
    {
        $validation->hasKey('to', 'Copy action needs a to argument');
        $validation->hasKey('from', 'Copy action needs a from argument');
    }

    private function getDirectoryContents(ShellProviderInterface $shell, $install_dir): array
    {
        $contents = $shell->run('ls -1a '.$install_dir, RunOptions::CAPTURE_AND_HIDE_OUTPUT);

        return array_filter($contents->getOutput(), function ($elem) {
            return !in_array($elem, ['.', '..']);
        });
    }

    protected function runImplementation(
        HostConfig $host_config,
        TaskContextInterface $context,
        ShellProviderInterface $shell,
        string $install_dir,
        string $target_dir,
    ) {
        $shell->pushWorkingDir($install_dir);

        $files_to_copy = $this->getArgument('from');
        if (!is_array($files_to_copy)) {
            if ('*' == $files_to_copy) {
                $files_to_copy = $this->getDirectoryContents($shell, $install_dir);
            } else {
                $files_to_copy = [$files_to_copy];
            }
        }

        $files_to_skip = $context->getConfigurationService()->getSetting('excludeFiles.gitSync', []);

        // Make sure that git-related files are skipped.
        $files_to_skip[] = '.git';
        $to = $target_dir.'/'.$this->getArgument('to');

        // Make sure the target directory exists before copying.
        $shell->run(sprintf('mkdir -p %s', $to));

        foreach ($files_to_copy as $file) {
            if (!in_array($file, $files_to_skip)) {
                $shell->run(sprintf('rm -rf %s', $to.'/'.basename($file)));
                $shell->run(sprintf('cp -a %s %s', $file, $to));
            }
        }

        $shell->popWorkingDir();
    }
}
