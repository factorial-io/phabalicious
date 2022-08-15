<?php /** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Command;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Method\MethodFactory;

class YarnCommand extends SimpleExecutableInvocationCommand
{
    public function __construct(ConfigurationService $configuration, MethodFactory $method_factory)
    {
        parent::__construct($configuration, $method_factory, 'yarn');
    }

    protected function configure()
    {
        parent::configure();
        $this->setHelp('
Run a yarn command in a specific context. Currently three contexts are supported:

- the <info>host-context</info> (standard), which will execute yarn in the same context as the
  app
- the <info>docker-host</info>-context, which will execute yarn in the same context as where
  docker commands executed (usually the parent)
- the <info>docker-image</info>-context, which is piggypacking the script execution context and
  run the command inside a docker-container derived from a given docker-image.
  Suitable, when yarn/ node is not available on the host.

<options=bold>Configuration:</>
Yarn configuation is per host

    hosts:
      example:
        additionalNeeds:
          - yarn
        yarn:
          # the build command to execute on install and reset
          buildCommand: build:prod
          context: (host|docker-host|docker-image)
          # for the docker-image context you can add additional configuration
          # see script execution context for more info
          image: node: 16
        ...


<options=bold>Examples:</>
<info>phab -cconfig yarn build</info>

        ');
    }
}
