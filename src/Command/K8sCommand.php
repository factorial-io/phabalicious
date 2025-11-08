<?php /** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Command;

use Phabalicious\Exception\BlueprintTemplateNotFoundException;
use Phabalicious\Exception\FabfileNotFoundException;
use Phabalicious\Exception\FabfileNotReadableException;
use Phabalicious\Exception\MethodNotFoundException;
use Phabalicious\Exception\MismatchedVersionException;
use Phabalicious\Exception\MissingDockerHostConfigException;
use Phabalicious\Exception\ShellProviderNotFoundException;
use Phabalicious\Exception\TaskNotFoundInMethodException;
use Phabalicious\Method\K8sMethod;
use Phabalicious\ShellProvider\CommandResult;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class K8sCommand extends BaseCommand
{
    public function completeArgumentValues($argumentName, CompletionContext $context): array
    {
        if ($argumentName == 'k8s') {
            return K8sMethod::AVAILABLE_SUB_COMMANDS;
        }
        return parent::completeArgumentValues($argumentName, $context);
    }

    protected function configure()
    {
        parent::configure();
        $this
            ->setName('k8s')
            ->setDescription('Run a k8s command')
            ->setHelp('
Runs Kubernetes commands against the configured cluster.

This command provides integration with Kubernetes, allowing you to run
kubectl and custom k8s commands on hosts deployed to Kubernetes clusters.

Behavior:
- Requires host configuration with Kubernetes integration enabled
- Executes kubectl or custom k8s commands in the context of the configured cluster
- Passes all arguments to the k8s command handler
- Returns the exit code from the command

The host configuration must have Kubernetes properly configured,
including cluster context, namespace, and authentication details.

Arguments:
- <k8s>: The Kubernetes command and arguments to run

Examples:
<info>phab --config=myconfig k8s get pods</info>
<info>phab --config=production k8s logs <pod-name></info>
<info>phab --config=myconfig k8s describe deployment</info>
<info>phab --config=myconfig k8s exec <pod> -- ls</info>
            ');
        $this->addArgument(
            'k8s',
            InputArgument::REQUIRED | InputArgument::IS_ARRAY,
            'The k8s-command to run'
        );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int

     * @throws BlueprintTemplateNotFoundException
     * @throws FabfileNotFoundException
     * @throws FabfileNotReadableException
     * @throws MethodNotFoundException
     * @throws MismatchedVersionException
     * @throws MissingDockerHostConfigException
     * @throws ShellProviderNotFoundException
     * @throws TaskNotFoundInMethodException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($result = parent::execute($input, $output)) {
            return $result;
        }

        $context = $this->getContext();
        $subcommands = $input->getArgument('k8s');
        if (!is_array($subcommands)) {
            $subcommands = [ $subcommands ];
        }
        $context->set('command', implode(' ', $subcommands));

        // Allow methods to override the used shellProvider:
        $host_config = $this->getHostConfig();
        $this->getMethods()->runTask('k8s', $host_config, $context);

        /** @var CommandResult $result */
        $result = $context->getResult('commandResult', new CommandResult(0, []));

        return $result->getExitCode();
    }
}
