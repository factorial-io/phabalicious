<?php /** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Command;

use InvalidArgumentException;
use Phabalicious\Exception\BlueprintTemplateNotFoundException;
use Phabalicious\Exception\EarlyTaskExitException;
use Phabalicious\Exception\FabfileNotFoundException;
use Phabalicious\Exception\FabfileNotReadableException;
use Phabalicious\Exception\MethodNotFoundException;
use Phabalicious\Exception\MismatchedVersionException;
use Phabalicious\Exception\MissingDockerHostConfigException;
use Phabalicious\Exception\MissingHostConfigException;
use Phabalicious\Exception\ShellProviderNotFoundException;
use Phabalicious\Exception\TaskNotFoundInMethodException;
use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\Method\DatabaseMethod;
use Phabalicious\Method\TaskContext;
use Phabalicious\Utilities\Utilities;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
        $this->addOption(
            'skip-reset',
            null,
            InputOption::VALUE_OPTIONAL,
            'Skip the reset-task after importind the db',
            false
        );
        $this->addOption(
            'skip-drop-db',
            null,
            InputOption::VALUE_OPTIONAL,
            'Skip dropping the db before running the import',
            false
        );
        $this->setAliases(['copyFrom']);
    }

    public function completeArgumentValues($argumentName, CompletionContext $context)
    {
        if ($argumentName == 'from') {
            $data = $this->configuration->getAllHostConfigs();
            return $data ? array_keys($data) : [];
        } elseif ($argumentName == 'what') {
            return [
                'db',
                'files'
            ];
        }
        return [];
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int|null
     * @throws BlueprintTemplateNotFoundException
     * @throws FabfileNotFoundException
     * @throws FabfileNotReadableException
     * @throws MethodNotFoundException
     * @throws MismatchedVersionException
     * @throws MissingDockerHostConfigException
     * @throws MissingHostConfigException
     * @throws ShellProviderNotFoundException
     * @throws TaskNotFoundInMethodException
     * @throws ValidationFailedException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($result = parent::execute($input, $output)) {
            return $result;
        }

        $context = $this->getContext();
        $from = $this->configuration->getHostConfig($input->getArgument('from'));
        if (empty($from['supportsCopyFrom'])) {
            throw new InvalidArgumentException('Source config does not support copy-from!');
        }

        $context->set('from', $from);
        $what = array_map(function ($elem) {
            return trim(strtolower($elem));
        }, $input->getArgument('what'));

        $context->set('what', $what);
        $next_tasks = !in_array('db', $what) || Utilities::hasBoolOptionSet($input, 'skip-reset')
            ? []
            : ['reset'];

        $context->set(DatabaseMethod::DROP_DATABASE, !Utilities::hasBoolOptionSet($input, 'skip-drop-db'));
        try {
            $this->getMethods()->runTask('copyFromPrepareSource', $from, $context);
            $this->getMethods()->runTask('copyFrom', $this->getHostConfig(), $context, $next_tasks);
        } catch (EarlyTaskExitException $e) {
            return 1;
        }

        return $context->getResult('exitCode', 0);
    }
}
