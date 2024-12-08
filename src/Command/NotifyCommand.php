<?php

/** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Command;

use Phabalicious\Exception\MethodNotFoundException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class NotifyCommand extends BaseCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('notify')
            ->setDescription('Will send a notification')
            ->addArgument(
                'message',
                InputArgument::REQUIRED,
                'The message to send'
            )
            ->addOption(
                'channel',
                false,
                InputOption::VALUE_OPTIONAL,
                'The channel to send the message to'
            )
            ->setHelp('Send a custom message as notification.');
    }

    /**
     * @throws MethodNotFoundException
     * @throws \Phabalicious\Exception\BlueprintTemplateNotFoundException
     * @throws \Phabalicious\Exception\FabfileNotFoundException
     * @throws \Phabalicious\Exception\FabfileNotReadableException
     * @throws \Phabalicious\Exception\MismatchedVersionException
     * @throws \Phabalicious\Exception\MissingDockerHostConfigException
     * @throws \Phabalicious\Exception\ShellProviderNotFoundException
     * @throws \Phabalicious\Exception\TaskNotFoundInMethodException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($result = parent::execute($input, $output)) {
            return $result;
        }
        $context = $this->getContext();
        $context->set('message', $input->getArgument('message'));
        $context->set('channel', $input->getOption('channel'));

        $this->getMethods()->runTask('notify', $this->getHostConfig(), $context);

        return $context->getResult('exitCode', 0);
    }
}
