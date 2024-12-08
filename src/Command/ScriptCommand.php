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
    protected static $defaultName = 'about';

    protected function configure()
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
            ->setHelp(
                'Runs a script from the global section or from a given host-config. '.
                'If you skip the script-option all available scripts were listed.'
            );
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
