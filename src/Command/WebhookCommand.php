<?php

namespace Phabalicious\Command;

use Phabalicious\Exception\BlueprintTemplateNotFoundException;
use Phabalicious\Exception\FabfileNotFoundException;
use Phabalicious\Exception\FabfileNotReadableException;
use Phabalicious\Exception\MethodNotFoundException;
use Phabalicious\Exception\MismatchedVersionException;
use Phabalicious\Exception\MissingDockerHostConfigException;
use Phabalicious\Exception\ShellProviderNotFoundException;
use Phabalicious\Exception\TaskNotFoundInMethodException;
use Phabalicious\Method\TaskContext;
use Phabalicious\ShellCompletion\FishShellCompletionContext;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class WebhookCommand extends BaseCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('webhook')
            ->setDescription('The webhook to invoke')
            ->addArgument(
                'webhook',
                InputArgument::OPTIONAL,
                'The webhook to invoke'
            )
            ->addOption(
                'arguments',
                'a',
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'Pass optional arguments to the webhook'
            )
            ->setHelp('Invokes a webhook from the global section.');
    }

    public function completeArgumentValues($argumentName, CompletionContext $context)
    {
        if (($argumentName == 'webhook') && ($context instanceof FishShellCompletionContext)) {
            $scripts = $this->getConfiguration()->getSetting('webhooks', []);
            return array_keys($scripts);
        }
        parent::completeArgumentValues($argumentName, $context);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     * @throws BlueprintTemplateNotFoundException
     * @throws FabfileNotFoundException
     * @throws FabfileNotReadableException
     * @throws MethodNotFoundException
     * @throws MismatchedVersionException
     * @throws MissingDockerHostConfigException
     * @throws TaskNotFoundInMethodException
     * @throws ShellProviderNotFoundException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($result = parent::execute($input, $output)) {
            return $result;
        }
        if (!$input->getArgument('webhook')) {
            $this->listAllWebhooks($output);
            return 1;
        } else {
            $webhook_name = $input->getArgument('webhook');
            $arguments = $this->parseScriptArguments([], $input->getOption('arguments'));
            if (!empty($script_data['script'])) {
                $script_data = $script_data['script'];
            }

            $context = new TaskContext($this, $input, $output);
            $context->set('variables', $arguments);
            $context->set('webhook_name', $webhook_name);

            $this->getMethods()->call('webhook', 'webhook', $this->getHostConfig(), $context);
            $result = $context->getResult('webhook_result', false);
            if (!$result) {
                throw new \RuntimeException(sprintf('Could not find webhook `%s`', $webhook_name));
            }

            $output->writeln($result);
        }

        return $context->getResult('exitCode', 0);
    }

    private function listAllWebhooks(OutputInterface $output)
    {
        $webhooks = $this->getConfiguration()->getSetting('webhooks', []);
        $output->writeln('<options=bold>Available webhooks</>');
        foreach ($webhooks as $name => $webhook) {
            if ($name == 'defaults') {
                continue;
            }
            $output->writeln('  - ' . $name);
        }
    }
}
