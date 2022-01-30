<?php

namespace Phabalicious\Command;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Configuration\Storage\Node;
use Phabalicious\Method\TaskContext;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AboutCommand extends BaseCommand
{
    protected static $defaultName = 'about';

    protected function configure()
    {
        parent::configure();
        $this
            ->setName('about')
            ->setDescription('Shows the configuration')
            ->setHelp('Shows a detailed view of all configuration of that specific host');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
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
        $context = $this->createContext($input, $output);

        if ($result = parent::execute($input, $output)) {
            return $result;
        }

        $header = ['Key', 'Value'];
        $verbose = $output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL;
        if ($verbose) {
            $header[] = 'Inherited from';
        }

        $context->io()->title('Configuration of ' . $this->getHostConfig()->getConfigName());
        $rows = [];
        $this->getRows($rows, $this->getHostConfig()->getData(), $verbose);
        $context->io()->table($header, $rows);


        if ($this->getDockerConfig()) {
            $context->io()->title('Docker-configuration of ' . $this->getHostConfig()->getConfigName());
            $rows = [];
            $this->getRows($rows, $this->getDockerConfig()->getData(), $verbose);
            $context->io()->table($header, $rows);
        }

        $context = $this->getContext();
        $this->getMethods()->runTask('about', $this->getHostConfig(), $context);
    }

    private function getRows(&$rows, Node $node, $verbose, $stack = [])
    {
        foreach ($node as $key => $value) {
            $stack[] = $key;
            $row = [
               str_pad(' ', 2 * count($stack)) . implode('.', $stack),
               $value->isArray() ? '' : $value->getValue()
            ];
            if ($verbose) {
                $row[] = $value->getSource()->getSource();
            }
            $rows[] = $row;
            if ($value->isArray()) {
                $this->getRows($rows, $value, $verbose, $stack);
            }
            array_pop($stack);
        }
    }

    private function write(OutputInterface $output, array $data, int $level = 0)
    {
        ksort($data);
        foreach ($data as $key => $value) {
            if (is_numeric($key)) {
                $key = '-';
            }
            if (is_array($value)) {
                $output->writeln(str_pad('', $level) . str_pad($key, 30 - $level) . ' : ');
                $this->write($output, $value, $level + 2);
            } else {
                $output->writeln(str_pad('', $level) . str_pad($key, 30 - $level) . ' : ' . $value);
            }
        }
    }
}
