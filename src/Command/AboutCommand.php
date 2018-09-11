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
            ->setDescription('shows the configuration')
            ->setHelp('Shows a detailed view of all configuration of that specific host');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($result = parent::execute($input, $output)) {
            return $result;
        }



        $output->writeln('<options=bold>Configuration of ' . $this->getHostConfig()['config_name'] . '</>');
        $this->print($output, $this->getHostConfig()->raw());
        if ($this->getDockerConfig()) {
            $output->writeln('<options=bold>Docker configuration:</>');
            $this->print($output, $this->getDockerConfig(), 2);
        }

        $context = new TaskContext($this->getConfiguration(), $output);
        $this->getMethods()->runTask('about', $this->getHostConfig(), $context);
    }

    private function print(OutputInterface $output, array $data, int $level = 0)
    {
        ksort($data);
        foreach ($data as $key => $value) {
            if (is_numeric($key)) {
                $key = '-';
            }
            if (is_array($value)) {
                $output->writeln(str_pad('', $level) . str_pad($key, 30 - $level) . ' : ');
                $this->print($output, $value, $level + 2);
            } else {
                $output->writeln(str_pad('', $level) . str_pad($key, 30 - $level) . ' : ' . $value);
            }
        }
    }


}