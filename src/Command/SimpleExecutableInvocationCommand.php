<?php /** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Command;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Exception\BlueprintTemplateNotFoundException;
use Phabalicious\Exception\EarlyTaskExitException;
use Phabalicious\Exception\FabfileNotFoundException;
use Phabalicious\Exception\FabfileNotReadableException;
use Phabalicious\Exception\MethodNotFoundException;
use Phabalicious\Exception\MismatchedVersionException;
use Phabalicious\Exception\MissingDockerHostConfigException;
use Phabalicious\Exception\ShellProviderNotFoundException;
use Phabalicious\Exception\TaskNotFoundInMethodException;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\TaskContext;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class SimpleExecutableInvocationCommand extends BaseCommand
{
    protected $executableName;

    protected $runInteractively = false;

    public function __construct(
        ConfigurationService $configuration,
        MethodFactory $method_factory,
        $executable_name,
        $run_interactively = false
    ) {
        $this->executableName = $executable_name;
        $this->runInteractively = $run_interactively;
        parent::__construct($configuration, $method_factory);
    }

    protected function configure()
    {
        parent::configure();
        $this
            ->setName($this->executableName)
            ->setDescription('Runs ' . $this->executableName)
            ->setHelp('Runs a ' . $this->executableName . ' command against the given host-config');
        $this->addArgument(
            'command-arguments',
            InputArgument::REQUIRED | InputArgument::IS_ARRAY,
            'The command to run'
        );
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
     * @throws ShellProviderNotFoundException
     * @throws TaskNotFoundInMethodException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($result = parent::execute($input, $output)) {
            return $result;
        }

        $context = $this->getContext();

        $arguments = $this->prepareArguments($input->getArgument('command-arguments'));
        $context->set('command', $arguments);

        try {
            $this->getMethods()->runTask($this->getTaskName(), $this->getHostConfig(), $context);
        } catch (EarlyTaskExitException $e) {
            return 1;
        }

        if ($this->runInteractively) {
            $shell = $context->getResult('shell', $this->getHostConfig()->shell());
            $command = $context->getResult('command');

            if (!$command) {
                throw new \RuntimeException(sprintf(
                    'No command-arguments returned for %s-command!',
                    $this->executableName
                ));
            }

            $context->io()->comment(sprintf(
                'Starting %s on `%s`',
                $this->executableName,
                $this->getHostConfig()['config_name']
            ));

            $options = $this->getSuitableShellOptions($output);
            $process = $this->startInteractiveShell($context->io(), $shell, $command, $options);
            return $process->getExitCode();
        } else {
            return $context->getResult('exitCode', 0);
        }
    }

    /**
     * Convert executable name to a taskname.
     */
    protected function getTaskName()
    {
        return str_replace(' ', '', lcfirst(ucwords(str_replace('-', ' ', $this->executableName))));
    }
}
