<?php

namespace Phabalicious\CustomPlugin;

use Phabalicious\Command\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CustomCommand extends BaseCommand
{
    public function configure()
    {
        parent::configure();
        $this->setName('custom');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        if ($result = parent::execute($input, $output)) {
            return $result;
        }
        $output->writeln("hello world");
        return 0;
    }
}
