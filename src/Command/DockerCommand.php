<?php /** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Command;

use Phabalicious\Exception\EarlyTaskExitException;
use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\Method\TaskContext;
use Phabalicious\Utilities\Utilities;
use Phabalicious\Validation\ValidationErrorBag;
use Phabalicious\Validation\ValidationService;
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
     * @throws \Phabalicious\Exception\TooManyShellProvidersException
     * @throws ValidationFailedException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($result = parent::execute($input, $output)) {
            return $result;
        }

        $context = new TaskContext($this, $input, $output);
        $docker_config = $this->getDockerConfig();
        $context->set('docker_config', $docker_config);

        $tasks = $input->getArgument('docker');
        if (!is_array($tasks)) {
            $tasks = [$tasks];
        }

        foreach ($tasks as $task) {
            $errors = new ValidationErrorBag();
            $validation = new ValidationService($docker_config['tasks'], $errors, 'dockerConfig');
            $validation->hasKey($task, 'Could not find task!');
            if ($errors->hasErrors()) {
                $this->printAvailableTasks($input, $output, $docker_config['tasks']);
                throw new ValidationFailedException($errors);
            }
            try {
                $context->set('docker_task', $task);

                $this->getMethods()
                    ->runTask('docker', $this->getHostConfig(), $context);
            } catch (EarlyTaskExitException $e) {
                return 1;
            }
        }

        return $context->get('exitCode', 0);
    }

    private function printAvailableTasks(InputInterface $input, OutputInterface $output, $tasks)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('List of docker tasks:');
        $io->listing(array_keys($tasks));
    }

}
