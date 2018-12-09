<?php

namespace Phabalicious\ShellCompletion;

use Stecman\Component\Symfony\Console\BashCompletion\Completion\CompletionAwareInterface;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Descriptor\ApplicationDescription;
use Symfony\Component\Console\Descriptor\Descriptor;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

class FishShellCompletionDescriptor extends Descriptor
{

    private $context;

    public function __construct()
    {
        $this->context = new CompletionContext();
    }

    /**
     * Describes an InputArgument instance.
     *
     * @param InputArgument $argument
     * @param array $options
     * @return string|mixed
     */
    protected function describeInputArgument(InputArgument $argument, array $options = array())
    {
        global $argv;
        $command = $options['command'];
        if (!$command instanceof CompletionAwareInterface) {
            return;
        }
        $this->output->write(
            "complete -c phab -n '__fish_seen_subcommand_from " . $command->getName() .
            "' -f"
        );
        global $argv;

        $this->output->write(
            " -a '(__fish_phab_get_arguments " .
            $command->getName() . ' ' .
            $argument->getName() .
            ")'"
        );
        $this->output->writeln(
            " -d '" .
            $argument->getDescription() .
            "'"
        );
    }

    /**
     * Describes an InputOption instance.
     *
     * @param InputOption $option
     * @param array $options
     * @return string|mixed
     */
    protected function describeInputOption(InputOption $option, array $options = array())
    {
        global $argv;
        $command = $options['command'];
        $this->output->write(
            "complete -c phab -n '__fish_seen_subcommand_from " . $command->getName() .
            "' -f"
        );

        if (strlen($option->getShortcut()) > 0) {
            if (strlen($option->getShortcut()) == 1) {
                $this->output->write(" -s ");
            } else {
                $this->output->write(" -o ");
            }
            $this->output->write($option->getShortcut());
        }

        $this->output->write(
            " -l " .
            $option->getName()
        );

        $this->output->writeln(
            " -d '" .
            $option->getDescription() .
            "'"
        );
        if ($command instanceof CompletionAwareInterface) {
            global $argv;
            $this->output->write(
                "complete -c phab -n '__fish_seen_subcommand_from " .
                $command->getName() .
                "; and __fish_phab_seen_argument -l " .
                $option->getName() .
                "' -f"
            );

            if ($option->isValueRequired()) {
                $this->output->write(" -r ");
            }

            $this->output->writeln(
                " -a '(__fish_phab_get_options " .
                $command->getName() . ' ' .
                $option->getName() .
                ")'"
            );
        }
    }

    /**
     * Describes an InputDefinition instance.
     *
     * @param InputDefinition $definition
     * @param array $options
     * @return string|mixed
     */
    protected function describeInputDefinition(InputDefinition $definition, array $options = array())
    {
    }

    /**
     * Describes a Command instance.
     *
     * @param Command $command
     * @param array $options
     * @return string|mixed
     */
    protected function describeCommand(Command $command, array $options = array())
    {
        global $argv;

        $this->output->writeln(
            "complete -c phab -n '__fish_use_subcommand' -f -a " .
            $command->getName() .
            " -d '" .
            $command->getDescription() .
            "'"
        );
        foreach ($command->getDefinition()->getOptions() as $option) {
            $this->describeInputOption($option, ['command' => $command]);
        }
        foreach ($command->getDefinition()->getArguments() as $argument) {
            $this->describeInputArgument($argument, ['command' => $command]);
        }
    }

    /**
     * Describes an Application instance.
     *
     * @param Application $application
     * @param array $options
     * @return string|mixed
     */
    protected function describeApplication(Application $application, array $options = array())
    {
        global $argv;
        $this->output->writeln('complete -c phab -e');
        $this->output->writeln('function __fish_phab_get_options');
        $this->output->writeln('  set -l CMD (commandline -ocp)');
        $this->output->writeln('  ' . $argv[0] .
            ' _completion --complete-command $argv[1]  --complete-option $argv[2] --command-line "$CMD" ');
        $this->output->writeln('end');

        $this->output->writeln('function __fish_phab_get_arguments');
        $this->output->writeln('  set -l CMD (commandline -ocp)');
        $this->output->writeln('  ' . $argv[0] .
            ' _completion --complete-command $argv[1]  --complete-argument $argv[2] --command-line "$CMD" ');
        $this->output->writeln('end');
        $this->output->writeln('function __fish_phab_seen_argument');
        $this->output->writeln('	argparse \'s/short=+\' \'l/long=+\' -- $argv');

        $this->output->writeln('	set cmd (commandline -co)');
        $this->output->writeln('	set -e cmd[1]');
        $this->output->writeln('	for t in $cmd');
        $this->output->writeln('		for s in $_flag_s');
        $this->output->writeln('			if string match -qr "^-[A-z0-9]*"$s"[A-z0-9]*\$" -- $t');
        $this->output->writeln('				return 0');
        $this->output->writeln('			end');
        $this->output->writeln('		end');
        $this->output->writeln('');
        $this->output->writeln('		for l in $_flag_l');
        $this->output->writeln('			if string match -q -- "--$l" $t');
        $this->output->writeln('				return 0');
        $this->output->writeln('			end');
        $this->output->writeln('		end');
        $this->output->writeln('	end');
        $this->output->writeln('');
        $this->output->writeln('	return 1');
        $this->output->writeln('end');

        $describedNamespace = isset($options['namespace']) ? $options['namespace'] : null;
        $description = new ApplicationDescription($application, $describedNamespace);
        foreach ($description->getCommands() as $command) {
            $this->describe($this->output, $command);
        }
    }
}
