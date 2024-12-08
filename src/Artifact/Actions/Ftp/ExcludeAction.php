<?php

namespace Phabalicious\Artifact\Actions\Ftp;

use Phabalicious\Artifact\Actions\ActionBase;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Validation\ValidationService;

class ExcludeAction extends ActionBase
{
    public const FTP_SYNC_EXCLUDES = 'ftpSyncExcludes';

    protected function validateArgumentsConfig(array $action_arguments, ValidationService $validation)
    {
    }

    public function run(HostConfig $host_config, TaskContextInterface $context)
    {
        $to_exclude = $this->getArguments();
        $existing = $context->getResult(self::FTP_SYNC_EXCLUDES, []);
        $to_exclude = array_unique(array_merge(
            $context->getConfigurationService()->getSetting('excludeFiles.ftpSync', []),
            $existing,
            $to_exclude
        ));
        $context->setResult(self::FTP_SYNC_EXCLUDES, $to_exclude);
    }
}
