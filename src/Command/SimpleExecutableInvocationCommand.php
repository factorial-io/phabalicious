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

    public function __construct(ConfigurationService $configuration, MethodFactory $method_factory, $executable_name)
    {
        $this->executableName = $executable_name;
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

        $context = $this->createContext($input, $output);

        $arguments = $this->prepareArguments($input->getArgument('command-arguments'));
        $context->set('command', $arguments);

        try {
            $this->getMethods()->runTask($this->getTaskName(), $this->getHostConfig(), $context);
        } catch (EarlyTaskExitException $e) {
            return 1;
        }

        return $context->getResult('exitCode', 0);
    }

    /**
     * Convert executable name to a taskname.
     */
    protected function getTaskName()
    {
        return str_replace(' ', '', ucwords(str_replace('-', ' ', $this->executableName)));
    }
}
