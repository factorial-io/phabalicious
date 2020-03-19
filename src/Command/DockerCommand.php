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

class DockerCommand extends BaseCommand
{

    protected function configure()
    {
        parent::configure();
        $this
            ->setName('docker')
            ->setDescription('Run one or more specific docker tasks')
            ->addArgument(
                'docker',
                InputArgument::IS_ARRAY | InputArgument::REQUIRED,
                'docker tasks to run'
            )
            ->setHelp('Run one or more specific docker tasks');
    }

    public function completeArgumentValues($argumentName, CompletionContext $context)
    {
        if (($argumentName == 'docker') && ($context instanceof FishShellCompletionContext)) {
            $host_config = $context->getHostConfig();
            if ($host_config) {
                $docker_config = $context->getDockerConfig($host_config['configName']);
                return array_keys($this->getAllTasks($docker_config));
            }
        }
        parent::completeArgumentValues($argumentName, $context); // TODO: Change the autogenerated stub
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
     * @throws \Phabalicious\Exception\ShellProviderNotFoundException
     * @throws \Phabalicious\Exception\TaskNotFoundInMethodException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($result = parent::execute($input, $output)) {
            return $result;
        }

        $context = $this->createContext($input, $output);
        $docker_config = $this->getDockerConfig();
        $context->set('docker_config', $docker_config);


        $tasks = $input->getArgument('docker');
        if (!is_array($tasks)) {
            $tasks = [$tasks];
        }
        try {
            $all_tasks = $this->getAllTasks($docker_config);
            foreach ($tasks as $task) {
                if (empty($all_tasks[$task])) {
                    $this->printAvailableTasks($input, $output, $all_tasks);
                    throw new MethodNotFoundException('Missing task `' . $task . '`');
                }
                $context->set('docker_task', $task);

                $this->getMethods()
                    ->runTask('docker', $this->getHostConfig(), $context);
            }

            return $context->getResult('exitCode', 0);
        } catch (EarlyTaskExitException $e) {
            return 1;
        }
    }

    /**
     * @param HostConfig $docker_config
     * @return array|mixed
     * @throws MethodNotFoundException
     */
    private function getAllTasks(HostConfig $docker_config)
    {
        $tasks = $docker_config['tasks'];
        /** @var DockerMethod $method */
        $method = $this->getConfiguration()->getMethodFactory()->getMethod('docker');
        $tasks = Utilities::mergeData($tasks, array_combine(
            $method->getInternalTasks(),
            $method->getInternalTasks()
        ));
        return $tasks;
    }

    private function printAvailableTasks(InputInterface $input, OutputInterface $output, $tasks)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('List of docker tasks:');
        $io->listing(array_keys($tasks));
    }
}
