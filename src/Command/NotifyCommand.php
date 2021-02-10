<?php /** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Command;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Exception\EarlyTaskExitException;
use Phabalicious\Exception\MethodNotFoundException;
use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\Method\DockerMethod;
use Phabalicious\Method\TaskContext;
use Phabalicious\ShellCompletion\FishShellCompletionContext;
use Phabalicious\Utilities\Utilities;
use Psr\Log\NullLogger;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

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
                InputOption::VALUE_OPTIONAL
            )
            ->setHelp('Send a custom message as notification.');
    }


    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int|null
     * @throws MethodNotFoundException
     * @throws \Phabalicious\Exception\BlueprintTemplateNotFoundException
     * @throws \Phabalicious\Exception\FabfileNotFoundException
     * @throws \Phabalicious\Exception\FabfileNotReadableException
     * @throws \Phabalicious\Exception\MismatchedVersionException
     * @throws \Phabalicious\Exception\MissingDockerHostConfigException
     * @throws \Phabalicious\Exception\ShellProviderNotFoundException
     * @throws \Phabalicious\Exception\TaskNotFoundInMethodException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
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
