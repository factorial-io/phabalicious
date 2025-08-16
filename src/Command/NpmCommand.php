<?php

/** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Command;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Method\MethodFactory;

class NpmCommand extends SimpleExecutableInvocationCommand
{
    public function __construct(ConfigurationService $configuration, MethodFactory $method_factory)
    {
        parent::__construct($configuration, $method_factory, 'npm');
    }

    protected function configure(): void
    {
        parent::configure();
        $this->setHelp('
Run a npm command in a specific context. Currently three contexts are supported:

- the <info>host-context</info> (standard), which will execute npm in the same context as the
  app
- the <info>docker-host</info>-context, which will execute npm in the same context as where
  docker commands executed (usually the parent)
- the <info>docker-image</info>-context, which is piggypacking the script execution context and
  run the command inside a docker-container derived from a given docker-image.
  Suitable, when npm/ node is not available on the host.

<options=bold>Configuration:</>
Npm configuation is per host

    hosts:
      example:
        additionalNeeds:
          - npm
        npm:
          # the build command to execute on install and reset
          buildCommand: build:prod
          context: (host|docker-host|docker-image|docker-image-on-docker-host)
          # for the docker-image context you can add additional configuration
          # see script execution context for more info
          image: node: 16
        ...


<options=bold>Examples:</>
<info>phab -cconfig npm build</info>

        ');
    }
}
