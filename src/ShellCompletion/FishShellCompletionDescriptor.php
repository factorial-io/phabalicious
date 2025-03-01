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
     */
    protected function describeInputArgument(InputArgument $argument, array $options = []): void
    {
        /** @var Command $command */
        $command = $options['command'];
        if (!$command instanceof CompletionAwareInterface) {
            return;
        }
        $this->output->write(
            "complete -c phab -n '__fish_seen_subcommand_from ".$command->getName().
            "' -f"
        );

        $this->output->write(
            " -a '(__fish_phab_get_arguments ".
            $command->getName().' '.
            $argument->getName().
            ")'"
        );
        $this->output->writeln(
            " -d '".
            $argument->getDescription().
            "'"
        );
    }

    /**
     * Describes an InputOption instance.
     */
    protected function describeInputOption(InputOption $option, array $options = []): void
    {
        /** @var Command $command */
        $command = $options['command'];
        $this->output->write(
            "complete -c phab -n '__fish_seen_subcommand_from ".$command->getName().
            "' -f"
        );

        if ($option->getShortcut() && strlen($option->getShortcut()) > 0) {
            if (1 == strlen($option->getShortcut())) {
                $this->output->write(' -s ');
            } else {
                $this->output->write(' -o ');
            }
            $this->output->write($option->getShortcut());
        }

        $this->output->write(
            ' -l '.
            $option->getName()
        );

        $this->output->writeln(
            ' -d '.
            escapeshellarg($option->getDescription())
        );
        if ($command instanceof CompletionAwareInterface) {
            $this->output->write(
                "complete -c phab -n '__fish_seen_subcommand_from ".
                $command->getName().
                '; and __fish_phab_seen_argument -l '.
                $option->getName().
                "' -f"
            );

            if ($option->isValueRequired()) {
                $this->output->write(' -r ');
            }

            $this->output->writeln(
                " -a '(__fish_phab_get_options ".
                $command->getName().' '.
                $option->getName().
                ")'"
            );
        }
    }

    /**
     * Describes an InputDefinition instance.
     */
    protected function describeInputDefinition(InputDefinition $definition, array $options = []): void
    {
    }

    /**
     * Describes a Command instance.
     */
    protected function describeCommand(Command $command, array $options = []): void
    {
        $this->output->writeln(
            "complete -c phab -n '__fish_use_subcommand' -f -a ".
            $command->getName().
            " -d '".
            $command->getDescription().
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
     */
    protected function describeApplication(Application $application, array $options = []): void
    {
        global $argv;
        $this->output->writeln('complete -c phab -e');
        $this->output->writeln('function __fish_phab_get_options');
        $this->output->writeln('  set -l CMD (commandline -ocp)');
        $this->output->writeln('  '.$argv[0].
            ' _completion --complete-command $argv[1]  --complete-option $argv[2] --command-line "$CMD" ');
        $this->output->writeln('end');

        $this->output->writeln('function __fish_phab_get_arguments');
        $this->output->writeln('  set -l CMD (commandline -ocp)');
        $this->output->writeln('  '.$argv[0].
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

        $describedNamespace = $options['namespace'] ?? null;
        $description = new ApplicationDescription($application, $describedNamespace);
        foreach ($description->getCommands() as $command) {
            $this->describe($this->output, $command);
        }
    }
}
