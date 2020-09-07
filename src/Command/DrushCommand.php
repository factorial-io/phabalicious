<?php /** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Command;

use Phabalicious\Method\TaskContext;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DrushCommand extends BaseCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('drush')
            ->setDescription('Runs drush')
            ->setHelp('Runs a drush command against the given host-config');
        $this->addArgument(
            'drush',
            InputArgument::REQUIRED | InputArgument::IS_ARRAY,
            'The drush-command to run'
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
        $arguments = $this->prepareArguments($input->getArgument('drush'));
        $context->set('command', $arguments);

        // Allow methods to override the used shellProvider:
        $host_config = $this->getHostConfig();
        $this->getMethods()->runTask('drush', $host_config, $context);
        $shell = $context->getResult('shell', $host_config->shell());
        $command = $context->getResult('command');

        if (!$command) {
            throw new \RuntimeException('No command-arguments returned for drush-command!');
        }

        $output->writeln('<info>Starting drush on `' . $host_config['configName'] . '`');

        $process = $this->startInteractiveShell($context->io(), $shell, $command, $output->isDecorated());
        return $process->getExitCode();
    }
}
