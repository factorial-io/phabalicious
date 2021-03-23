<?php

namespace Phabalicious\Command;

use Composer\Semver\Comparator;
use Humbug\SelfUpdate\Updater;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcher;

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
        $helper->text(sprintf(
            'Current version is %s, looking for a new version ...',
            $this->getApplication()->getVersion()
        ));

        $new_version = $this->runSelfUpdate($input->getOption('allow-unstable'));

        if ($new_version) {
            $helper->success(sprintf('Updated phabalicious successfully to %s', $new_version));

            // Exit early to prevent errors afterwards because of missing files in the phar.
            exit(0);
        } else {
            $helper->note('No newer version found!');
        }
    }

    protected static function getUpdater(Application $application, $allow_unstable)
    {
        $updater = new Updater(null, false);
        $updater->setStrategy(Updater::STRATEGY_GITHUB);

        $updater->getStrategy()->setPackageName('factorial-io/phabalicious');
        $updater->getStrategy()->setPharName('phabalicious.phar');
        $updater->getStrategy()->setCurrentLocalVersion($application->getVersion());
        $updater->getStrategy()->setStability($allow_unstable ? 'unstable' : 'stable');

        return $updater;
    }

    private function runSelfUpdate($allow_unstable)
    {
        $update_data = $this->hasUpdate($allow_unstable);
        if (!$update_data) {
            return false;
        }
        $allow_unstable = $update_data['unstable'];
        $updater = self::getUpdater($this->getApplication(), $allow_unstable);
        $result = $updater->update();
        return $result ? $updater->getNewVersion() : false;
    }

    public function hasUpdate($allow_unstable = false)
    {
        try {
            $version = $this->getApplication()->getVersion();
            $allow_unstable =
                $allow_unstable ||
                (stripos($version, 'alpha') !== false) ||
                (stripos($version, 'beta') !== false);

            $updater = self::getUpdater($this->getApplication(), $allow_unstable);

            if ($allow_unstable && !$updater->hasUpdate()) {
                // No new unstable version available, try again on the stable branch.
                $allow_unstable = false;
                $updater = self::getUpdater($this->getApplication(), $allow_unstable);
            }

            if (!$updater->hasUpdate()) {
                return false;
            }

            return [
                'new_version' => $updater->getNewVersion(),
                'unstable' => $allow_unstable,
            ];
        } catch (\Exception $e) {
            // Fallback, call github directly.
            return $this->getLatestReleaseFromGithub($allow_unstable);
        }

        return false;
    }


    protected function getLatestReleaseFromGithub($allow_unstable)
    {
        $payload = $this->getConfiguration()->readHttpResource(
            'https://api.github.com/repos/factorial-io/phabalicious/releases'
        );
        $releases = json_decode($payload);

        // Filter prereleases.
        if (!$allow_unstable) {
            $releases = array_filter($releases, function ($r) {
                return !$r->prerelease;
            });
            $releases = array_values($releases);
        }

        if (!isset($releases[0])) {
            throw new \Exception('API error - no release found at GitHub repository');
        }

        $version = $releases[0]->tag_name;

        if (Comparator::greaterThan($version, $this->getApplication()->getVersion())) {
            return [
                'new_version' => $version,
                'unstable' => false
            ];
        }

        return false;
    }

    public static function registerListener(EventDispatcher $dispatcher)
    {
        $dispatcher->addListener(ConsoleEvents::COMMAND, function (ConsoleCommandEvent $event) {

            $input = $event->getInput();
            $output = $event->getOutput();

            /** @var SelfUpdateCommand command */
            $command = $event->getCommand()->getApplication()->find('self-update');

            if (1 || $output->isDecorated()
                && !$output->isQuiet()
                && !$event->getCommand()->isHidden()
                && !$command->getConfiguration()->isOffline()
                && !$input->hasParameterOption(['--offline'])
                && !$input->hasParameterOption(['--no-interaction'])
            ) {
                if ($version = $command->hasUpdate()) {
                    $style = new SymfonyStyle($input, $output);
                    $style->block([
                        'Version ' . $version['new_version'] . ' of phabalicious is available. Run `phab self-update'
                        . ($version['unstable'] ? ' --allow-unstable=1' : '')
                        . '` to update your local installation.',
                        'Visit https://github.com/factorial-io/phabalicious/releases for more info.',
                    ], null, 'fg=white;bg=blue', ' ', true);
                }
            }
        });
    }
}
