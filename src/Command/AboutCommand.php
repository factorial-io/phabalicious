<?php

namespace Phabalicious\Command;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
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
        if ($result = parent::execute($input, $output)) {
            return $result;
        }

        $output->writeln('<options=bold>Configuration of ' . $this->getHostConfig()['configName'] . '</>');
        $this->write($output, $this->getHostConfig()->raw());
        if ($this->getDockerConfig()) {
            $output->writeln('<options=bold>Docker configuration:</>');
            $this->write($output, $this->getDockerConfig()->raw(), 2);
        }

        $context = $this->createContext($input, $output);
        $this->getMethods()->runTask('about', $this->getHostConfig(), $context);
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
