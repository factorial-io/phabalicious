<?php

namespace Phabalicious\Command;

use Humbug\SelfUpdate\Updater;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SelfUpdateCommand extends BaseOptionsCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('self-update')
            ->setDescription('Installs the latest version')
            ->setHelp('Downloads and install the latest version from github.');

        $this->addOption(
            'allow-unstable',
            null,
            InputOption::VALUE_OPTIONAL,
            'Allow updating to unstable versions',
            false
        );
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $helper = new SymfonyStyle($input, $output);
        $helper->text('Looking for a new version ...');

        $result = $this->runSelfUpdate($input->getOption('allow-unstable'));

        if ($result) {
            $helper->success('Updated phabalicious sucessfuly');
        } else {
            $helper->note('No newer version found!');
        }
    }

    private function runSelfUpdate($allow_unstable)
    {
        $updater = new Updater(null, false);
        $updater->setStrategy(Updater::STRATEGY_GITHUB);

        $updater->getStrategy()->setPackageName('factorial-io/phabalicious');
        $updater->getStrategy()->setPharName('phabalicious.phar');
        $updater->getStrategy()->setCurrentLocalVersion($this->getApplication()->getVersion());
        $updater->getStrategy()->setCurrentLocalVersion('3.0.0-alpha.1');
        $updater->getStrategy()->setStability($allow_unstable ? 'unstable' : 'stable');
        $result = $updater->update();

        return $result;
    }
}
