<?php /** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Command;

use Phabalicious\Exception\EarlyTaskExitException;
use Phabalicious\Method\TaskContext;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CopyFromCommand extends BaseCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('copy-from')
            ->setDescription('Copies database and/ or file from another instance')
            ->setHelp('Copies database and/ or files from another instance.');
        $this->addArgument(
            'from',
            InputArgument::REQUIRED,
            'From which instance to copy from'
        );
        $this->addArgument(
            'what',
            InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
            'What to to copy, allowed are `db` and `files`, if nothing is set, everything will be copied',
            ['files', 'db']
        );
        $this->setAliases(['copyFrom']);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int|null
     * @throws \Phabalicious\Exception\BlueprintTemplateNotFoundException
     * @throws \Phabalicious\Exception\FabfileNotFoundException
     * @throws \Phabalicious\Exception\FabfileNotReadableException
     * @throws \Phabalicious\Exception\MethodNotFoundException
     * @throws \Phabalicious\Exception\MismatchedVersionException
     * @throws \Phabalicious\Exception\MissingDockerHostConfigException
     * @throws \Phabalicious\Exception\MissingHostConfigException
     * @throws \Phabalicious\Exception\ShellProviderNotFoundException
     * @throws \Phabalicious\Exception\TaskNotFoundInMethodException
     * @throws \Phabalicious\Exception\ValidationFailedException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($result = parent::execute($input, $output)) {
            return $result;
        }

        $context = new TaskContext($this, $input, $output);
        $from = $this->configuration->getHostConfig($input->getArgument('from'));

        $context->set('from', $from);
        $context->set('what', array_map(function ($elem) {
            return trim(strtolower($elem));
        }, $input->getArgument('what')));

        try {
            $this->getMethods()->runTask('copyFrom', $this->getHostConfig(), $context);
        } catch (EarlyTaskExitException $e) {
            return 1;
        }

        return $context->getResult('exitCode', 0);
    }

}
