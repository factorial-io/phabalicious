<?php /** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Command;

use Phabalicious\Configuration\HostConfig;
use Phabalicious\Exception\EarlyTaskExitException;
use Phabalicious\Method\TaskContext;
use Phabalicious\Method\TaskContextInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DrupalConsoleCommand extends BaseCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('drupal')
            ->setDescription('Runs drupal console')
            ->setHelp('Runs a drupal console command');
        $this->addArgument(
            'drupal-command',
            InputArgument::REQUIRED,
            'The drupal-console-command to run'
        );
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
        $context->set('command', $input->getArgument('drupal-command'));
        $host_config = $this->getHostConfig();

        $this->getMethods()->runTask('drupalconsole', $host_config, $context);
        $shell = $context->getResult('shell', $host_config->shell());
        $command = $context->getResult('command');

        if (!$command) {
            throw new \RuntimeException('No command-arguments returned for drupal-command!');
        }

        $output->writeln('<info>Starting drupal-console on `' . $host_config['configName'] . '`');

        $process = $this->startInteractiveShell($context->io(), $shell, $command, $output->isDecorated());

        return $process->getExitCode();
    }
}
