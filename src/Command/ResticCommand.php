<?php

/** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Command;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Method\MethodFactory;

class ResticCommand extends SimpleExecutableInvocationCommand
{
    public function __construct(ConfigurationService $configuration, MethodFactory $method_factory)
    {
        parent::__construct($configuration, $method_factory, 'restic', true);
    }

    protected function configure()
    {
        parent::configure();
        $this->setHelp('

Provides integration with the restic command. Restic is used as a backup command
if configured correctly and enabled via <info>needs</info>. Restic will be executed in the
host-context, that means phab will create a shell for the given host-config and
executes the restic-command there. It will try to install restic if it can\'t
find an executable.

<options=bold>Configuration:</>
You can configure how restic is executed by adding the following snippet either
in the global scope, or in a host-configuration. Use a secret to prevent storing
sensitive data in the fabfile.

<info>secrets:
  restic-password:
    question: Password for offsite restic-repository?
    onePasswordId: xxx
    onePasswordVaultId: xxx
    tokenId: default

restic:
  # defaults:
  allowInstallation: 1
  downloadUrl: https://github.com/restic/restic/releases/download/v0.12.0/restic_0.12.0_linux_amd64.bz2
  options:
    - --verbose
  # required:
  repository: <url-to-your-repo>
  environment:
    RESTIC_PASSWORD: "%secret.restic-password%"</info>

Phab will include the repository, any options ir environment variables when
executing restic, so no need to add them by yourself. All command line arguments
will be passed to restic.


Examples:
<info>phab -cmy-config restic -- snapshots </info>
<info>phab -cmy-config restic -- restore <snapshot-id> </info>
<info>phab -cmy-config restic -- snapshots --host-name my-hostname</info>
<info>phab -cmy-config restic -- forget --prune --keep-daily=14 --keep-weekly=4 \
  --keep-monthly=6 --group-by host --prune</info>

       ');
    }
}
