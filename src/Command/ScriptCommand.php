<?php

namespace Phabalicious\Command;

use Phabalicious\Method\ScriptMethod;
use Phabalicious\ShellCompletion\FishShellCompletionContext;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ScriptCommand extends BaseCommand
{
    protected static $defaultName = 'script';

    protected function configure(): void
    {
        parent::configure();
        $this
            ->setName('script')
            ->setDescription('Runs a script from the global section or from a given host-config')
            ->addArgument(
                'script',
                InputArgument::OPTIONAL,
                'The script to run, if not given all possible scripts get listed.'
            )
            ->addOption(
                'arguments',
                'a',
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'Pass optional arguments to the script'
            )
            ->setHelp('
Runs a custom script defined in the global section or host configuration.

Scripts are custom automation tasks defined in your fabfile.yaml under the "scripts"
section. They can contain shell commands, method calls, or other phabalicious tasks
that you want to run as a single unit.

Behavior:
- If no <script> argument is provided, lists all available scripts
- If <script> is specified, runs that script
- Scripts can be defined globally (in the "scripts" section) or per-host
- Scripts can have defaults that override configuration values
- Arguments can be passed to the script using --arguments
- Throws an error if the specified script is not found

Scripts are useful for:
- Automating complex deployment workflows
- Creating custom maintenance tasks
- Combining multiple phabalicious commands
- Running project-specific operations

Arguments:
- <script>: Name of the script to run (optional)

Options:
- --arguments, -a: Pass key=value arguments to the script (can be used multiple times)

Examples:
<info>phab script</info>                                    # List all available scripts
<info>phab --config=myconfig script deploy-prod</info>      # Run the deploy-prod script
<info>phab script my-task --arguments foo=bar</info>        # Run script with arguments
<info>phab script cleanup -a dry-run=true -a verbose=1</info>
            ');
    }

    public function completeArgumentValues($argumentName, CompletionContext $context): array
    {
        if (('script' == $argumentName) && ($context instanceof FishShellCompletionContext)) {
            $scripts = $this->getConfiguration()->getSetting('scripts', []);
            $host_config = $context->getHostConfig();
            if ($host_config) {
                $host_scripts = !empty($host_config['scripts']) ? $host_config['scripts'] : [];

                return array_keys($scripts) + array_keys($host_scripts);
            }

            return array_keys($scripts);
        }

        return parent::completeArgumentValues($argumentName, $context);
    }

    /**
     * @throws \Phabalicious\Exception\BlueprintTemplateNotFoundException
     * @throws \Phabalicious\Exception\FabfileNotFoundException
     * @throws \Phabalicious\Exception\FabfileNotReadableException
     * @throws \Phabalicious\Exception\MethodNotFoundException
     * @throws \Phabalicious\Exception\MismatchedVersionException
     * @throws \Phabalicious\Exception\MissingDockerHostConfigException
     * @throws \Phabalicious\Exception\ShellProviderNotFoundException
     * @throws \Phabalicious\Exception\TaskNotFoundInMethodException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($result = parent::execute($input, $output)) {
            return $result;
        }
        if (!$input->hasArgument('script')) {
            $this->listAllScripts($output);

            return 0;
        } else {
            $script_name = $input->getArgument('script');
            $script_data = $this->findScript($script_name);
            if (!$script_data) {
                $this->listAllScripts($output);

                throw new \RuntimeException(sprintf('Could not find script `%s` in your fabfile!', $script_name));
            }

            $defaults = $script_data['defaults'] ?? [];
            $context = $this->createContext($input, $output, $defaults);
            ScriptMethod::prepareContextFromScript($context, $script_data);

            $this->getMethods()->call('script', 'runScript', $this->getHostConfig(), $context);
        }

        return $context->getResult('exitCode', 0);
    }

    private function listAllScripts(OutputInterface $output)
    {
        $scripts = $this->getConfiguration()->getSetting('scripts', []);
        $output->writeln('<options=bold>Available scripts</>');
        foreach ($scripts as $name => $script) {
            $output->writeln('  - '.$name);
        }
        if (isset($this->getHostConfig()['scripts'])) {
            foreach ($this->getHostConfig()['scripts'] as $name => $script) {
                $output->writeln('  - '.$name);
            }
        }
    }

    private function findScript($script_name)
    {
        return $this->getConfiguration()->findScript($this->getHostConfig(), $script_name);
    }
}
