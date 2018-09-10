<?php

namespace Phabalicious\Command;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Method\TaskContext;
use Phabalicious\Utilities\Utilities;
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
            ->setDescription('runs a script from the global section or from a given host-config')
            ->addArgument(
                'script',
                InputArgument::OPTIONAL,
                'The script to run, if not given all possible scripts get listed.'
            )
            ->addOption(
                'arguments',
                'a',
                InputArgument::OPTIONAL,
                'Pass optional arguments to the script'
            )
            ->setHelp(
                'Runs a script from the global section or from a given host-config. ' .
                'If you skip the script-option all available scripts were listed.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
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
                return 1;
            }
            $script_arguments = $this->parseScriptArguments($script_data, $input->getOption('arguments'));
            if (!empty($script_data['script'])) {
                $script_data = $script_data['script'];
            }

            $context = new TaskContext();
            $context->set('variables', $script_arguments);

            $this->getMethods()->call('script', 'runScript', $this->getHostConfig(), $context);
        }
    }

    private function listAllScripts(OutputInterface $output)
    {
        $scripts = $this->getConfiguration()->getSetting('scripts', []);
        $output->writeln('<options=bold>Available scripts</>');
        foreach ($scripts as $name => $script) {
            $output->writeln('  - ' . $name);
        }
        if (isset($this->getHostConfig()['scripts'])) {
            foreach ($this->getHostConfig()['scripts'] as $name => $script) {
                $output->writeln('  - ' . $name);
            }
        }
    }

    private function findScript($script_name)
    {
        $config = $this->getHostConfig();
        if (!empty($config['scripts'][$script_name])) {
            return $config['scripts'][$script_name];
        }
        return $this->getConfiguration()->getSetting('scripts.' . $script_name, false);
    }

    private function parseScriptArguments($script_data, $arguments_string)
    {
        $args = explode(' ', $arguments_string);
        $unnamed_args = array_filter($args, function ($elem) {
            return strpos($elem, '=') === false;
        });
        $temp = array_filter($args, function ($elem) {
            return strpos($elem, '=') !== false;
        });
        $named_args = [];
        foreach ($temp as $value) {
            $a = explode('=', $value);
            $named_args[$a[0]] = $a[1];
        }

        $defaults = !empty($script_data['defaults']) ? $script_data['defaults'] : [];

        return [
            'arguments' => Utilities::mergeData($defaults, $named_args),
            'combined' => implode(' ', $unnamed_args),
            'unnamed_arguments' => $unnamed_args,
        ];
    }

}