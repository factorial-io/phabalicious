<?php

namespace Phabalicious\Command;

use Phabalicious\Method\TaskContextInterface;
use Symfony\Component\Console\Input\InputInterface;

abstract class BackupBaseCommand extends BaseCommand
{
    protected function collectBackupMethods(InputInterface $input, TaskContextInterface $context)
    {
        $what = array_map(function ($elem) {
            return trim(strtolower($elem));
        }, $input->getArgument('what'));
        if (empty($what)) {
            $this->getMethods()->runTask('collectBackupMethods', $this->getHostConfig(), $context);
            $what = $context->getResult('backupMethods', []);
        }

        return $what;
    }

    protected function findBackupSet(string $hash, TaskContextInterface $context)
    {
        $this->getMethods()->runTask('listBackups', $this->getHostConfig(), $context);
        $backup_sets = $context->getResult('files');

        $result = array_filter($backup_sets, function ($elem) use ($hash) {
            return $elem['hash'] == $hash;
        });
        if (empty($result)) {
            throw new \InvalidArgumentException(sprintf('Could not find backup-set with hash `%s`', $hash));
        }

        return $result;
    }
}
