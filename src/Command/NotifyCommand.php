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
    protected function configure(): void
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
            ->setHelp('
Sends a custom notification message.

This command sends notifications through configured notification channels
(e.g., Slack, Mattermost, email, webhooks). Notification channels must be
configured in your fabfile under the "notifyOn" section.

Behavior:
- Sends the specified message through configured notification methods
- Can optionally target a specific channel
- Uses the host configuration\'s notification settings
- Returns success if notification is sent successfully

Notification channels are configured per host or globally in the fabfile.
Each method (e.g., mattermost, slack) can define how notifications are sent.

Arguments:
- <message>: The text message to send as a notification

Options:
- --channel: Specific channel to send to (depends on notification method configuration)

Examples:
<info>phab --config=myconfig notify "Deployment completed successfully"</info>
<info>phab --config=production notify "Starting maintenance" --channel="#ops"</info>
<info>phab notify "Build finished"</info>
            ');
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
