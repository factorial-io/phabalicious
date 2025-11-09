<?php

namespace Phabalicious\Artifact\Actions\Base;

use Phabalicious\Artifact\Actions\ActionBase;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Validation\ValidationService;

class ConfirmAction extends ActionBase
{
    protected function validateArgumentsConfig(array $action_arguments, ValidationService $validation)
    {
        $validation->hasKey('question', 'The confirm action needs a question to ask.');
    }

    public function run(HostConfig $host_config, TaskContextInterface $context)
    {
        if (!empty($context->getInput()->getOption('force'))) {
            return;
        }
        if (!$context->io()->confirm($this->getArgument('question'), false)) {
            throw new \RuntimeException('Cancelled by user!');
        }
    }
}
