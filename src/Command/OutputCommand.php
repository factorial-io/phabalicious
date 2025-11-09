<?php

namespace Phabalicious\Command;

use Phabalicious\Exception\BlueprintTemplateNotFoundException;
use Phabalicious\Exception\FabfileNotFoundException;
use Phabalicious\Exception\FabfileNotReadableException;
use Phabalicious\Exception\MismatchedVersionException;
use Phabalicious\Exception\MissingDockerHostConfigException;
use Phabalicious\Exception\MissingHostConfigException;
use Phabalicious\Exception\ShellProviderNotFoundException;
use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\Method\TaskContext;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Dumper;

class OutputCommand extends BaseCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this
            ->setName('output')
            ->setDescription('Outputs the configuration as yaml')
            ->setHelp('
Outputs the computed configuration as YAML or JSON.

This command displays the fully resolved and computed configuration for debugging
and inspection purposes. It shows how phabalicious interprets your fabfile after
applying all inheritance, blueprints, and transformations.

Behavior:
- Resolves all inheritance and configuration merging
- Expands blueprints if applicable
- Resolves secrets (shows actual secret values)
- Outputs in the specified format (YAML or JSON)

Options:
- --what: What to output (default: blueprint)
  - blueprint: Applied blueprint configuration (requires --blueprint)
  - host: Host configuration (single host)
  - docker: Docker configuration
  - global: Global fabfile settings
- --format: Output format (default: yaml)
  - yaml: YAML format
  - json: JSON format

Arguments:
- --config: Which host configuration to output
- --blueprint: Blueprint variant to expand (required for --what=blueprint)

Examples:
<info>phab --config=myconfig --blueprint=develop output</info>
<info>phab --config=myconfig output --what=host</info>
<info>phab --config=myconfig output --what=host --format=json</info>
<info>phab output --what=global</info>
<info>phab --config=myconfig output --what=docker</info>
            ');

        $this->addOption(
            'what',
            null,
            InputOption::VALUE_REQUIRED,
            'What to output: (blueprint|host|docker|global)',
            'blueprint'
        );

        $this->addOption(
            'format',
            null,
            InputOption::VALUE_REQUIRED,
            'Format to use for output: (yaml|json)',
            'yaml'
        );
    }

    /**
     * @throws BlueprintTemplateNotFoundException
     * @throws FabfileNotFoundException
     * @throws FabfileNotReadableException
     * @throws MismatchedVersionException
     * @throws MissingDockerHostConfigException
     * @throws MissingHostConfigException
     * @throws ShellProviderNotFoundException
     * @throws ValidationFailedException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = $input->getOption('config');
        $blueprint = $input->getOption('blueprint');
        $what = strtolower($input->getOption('what'));

        $available_options = ['blueprint', 'host', 'docker', 'global'];
        if (!in_array($what, $available_options)) {
            throw new \InvalidArgumentException(sprintf('Unknown option for `what`. Allowed values are %s', '`'.implode('`, `', $available_options).'`'));
        }

        $this->readConfiguration($input);
        $data = [];
        $title = '';
        $context = new TaskContext($this, $input, $output);

        if ('blueprint' == $what) {
            if (empty($blueprint)) {
                throw new \InvalidArgumentException('The required option --blueprint is not set or is empty');
            }
            $template = $this->getConfiguration()->getBlueprints()->getTemplate($config);
            $data = $template->expand($blueprint);
            $data = [
                $data['configName'] => $data->asArray(),
            ];
            $title = 'Output of applied blueprint `'.$config.'`';
        } elseif ('host' == $what) {
            if (!empty($blueprint)) {
                $data = $this->getConfiguration()->getHostConfigFromBlueprint($config, $blueprint);
            } else {
                $data = $this->getConfiguration()->getHostConfig($config);
            }
            $data = [
                $data['configName'] => $data->asArray(),
            ];
            $title = 'Output of host-configuration `'.$config.'`';
        } elseif ('docker' == $what) {
            try {
                $data = $this->getConfiguration()
                    ->getDockerConfig($config)
                    ->asArray();
            } catch (\Exception $e) {
                $host_config = $this->getConfiguration()->getHostConfig($config);
                $config = $host_config['docker']['configuration'];
                $data = $this->getConfiguration()->getDockerConfig($config)->asArray();
            }
            $data = [$config => $data];
            $title = 'Output of docker-configuration `'.$config.'`';
        } elseif ('global' == $what) {
            $title = 'Output of global configuration `'.$config.'`';
            $data = $this->getConfiguration()->getAllSettings();
        }

        $data = $this->configuration->getPasswordManager()->resolveSecrets($data);

        if ('json' == $input->getOption('format')) {
            $content = json_encode($data, JSON_PRETTY_PRINT);
        } else {
            $dumper = new Dumper(2);
            $content = $dumper->dump($data, 10, 2);
        }

        $io = new SymfonyStyle($input, $output);
        if ($output->isDecorated()) {
            $io->title($title);
        }

        $io->block(
            $content,
            null,
            null,
            ''
        );

        return 0;
    }
}
