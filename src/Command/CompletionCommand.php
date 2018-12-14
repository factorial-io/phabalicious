<?php

namespace Phabalicious\Command;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\ShellCompletion\FishShellCompletionContext;
use Phabalicious\ShellCompletion\FishShellCompletionDescriptor;
use Psr\Log\NullLogger;
use Stecman\Component\Symfony\Console\BashCompletion\Completion;
use Stecman\Component\Symfony\Console\BashCompletion\Completion\CompletionAwareInterface;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionHandler;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class CompletionCommand
 *
 * We are overriding CompletionCommand to provide rich fish-shell autocompletions without dynamic lookup.
 *
 * @package Phabalicious\Command
 */
class CompletionCommand extends \Stecman\Component\Symfony\Console\BashCompletion\CompletionCommand
{
    private $configuration;

    public function __construct(ConfigurationService $configuration)
    {
        parent::__construct();
        $this->configuration = $configuration;
    }
    
    protected function configureCompletion(CompletionHandler $handler)
    {
        $handler->addHandler(
            new Completion\ShellPathCompletion(
                Completion::ALL_COMMANDS,
                'fabfile',
                Completion::TYPE_OPTION
            )
        );
    }

    public function configure()
    {
        parent::configure(); // TODO: Change the autogenerated stub
        $this->addOption(
            'complete-option',
            '',
            InputOption::VALUE_OPTIONAL
        );
        $this->addOption(
            'complete-command',
            '',
            InputOption::VALUE_OPTIONAL
        );
        $this->addOption(
            'complete-argument',
            '',
            InputOption::VALUE_OPTIONAL
        );
        $this->addOption(
            'command-line',
            '',
            InputOption::VALUE_OPTIONAL
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->configuration->setLogger(new NullLogger());
        $this->handler = new CompletionHandler($this->getApplication());
        $shell_type = $input->getOption('shell-type') ?: $this->getShellType();

        if ($input->getOption('generate-hook')) {
            if ($shell_type == 'fish') {
                return $this->handleFishShellCompletions($output);
            }
        }

        if ($shell_type == 'fish') {
            $command_line = $input->getOption('command-line');
            $option = $input->getOption('complete-option');
            $command_name =$input->getOption('complete-command');
            $argument = $input->getOption('complete-argument');
            $command = $this->getApplication()->find($command_name);
            if (!$command || !($command instanceof CompletionAwareInterface)) {
                throw new \InvalidArgumentException('Could not find command '. $command_name);
            }

            $context = new FishShellCompletionContext($this->configuration, $this->getApplication(), $command_line);
            $return = false;

            if ($option) {
                $return = $command->completeOptionValues($option, $context);
            } if ($argument) {
                $return = $command->completeArgumentValues($argument, $context);
            }
            if (is_array($return)) {
                $output->writeln(implode("\n", $return));
                return 0;
            }
            return 1;
        }
        return parent::execute($input, $output);
    }

    private function handleFishShellCompletions(OutputInterface $output)
    {
        $helper = new FishShellCompletionDescriptor();
        $helper->describe($output, $this->getApplication());
    }
}
