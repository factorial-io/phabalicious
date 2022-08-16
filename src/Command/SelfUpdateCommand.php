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
        return 0;
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
        $use_unstable = $update_data['unstable'];
        $updater = self::getUpdater($this->getApplication(), $use_unstable);
        $result = $updater->update();
        return $result ? $updater->getNewVersion() : false;
    }

    public function hasUpdate($allow_unstable = false)
    {
        try {
            $version = $this->getApplication()->getVersion();
            // If the current running version is alpha/beta,
            // then the updater behaves is as if user intend to use
            // --allow-unstable.
            $use_unstable =
                $allow_unstable ||
                (stripos($version, 'alpha') !== false) ||
                (stripos($version, 'beta') !== false);

            $stable_updater = self::getUpdater($this->getApplication(), false);
            $updater = $stable_updater;
            if ($use_unstable) {
                $unstable_updater = self::getUpdater($this->getApplication(), true);
                $unstable_updater->hasUpdate(); // fetch version from github
                $stable_updater->hasUpdate(); // fetch version from github
                $unstable_version = $unstable_updater->getNewVersion();
                $stable_version = $stable_updater->getNewVersion();
                // Using an unstable version doesn't make sense if the latest
                // version is greater than the latest unstable version.
                // Hence, we try to update to unstable version
                // if and only if stable version is less than unstable version.
                if (Comparator::lessThan($stable_version, $unstable_version)) {
                    $updater = $unstable_updater;
                }
            }
            if (!$updater->hasUpdate()) {
                return false;
            }

            return [
                'new_version' => $updater->getNewVersion(),
                'unstable' => $use_unstable,
            ];
        } catch (\Exception $e) {
            // Fallback, call github directly.
            return $this->getLatestReleaseFromGithub($use_unstable);
        }

        return false;
    }


    protected function getLatestReleaseFromGithub($allow_unstable)
    {
        $payload = $this->getConfiguration()->readHttpResource(
            'https://api.github.com/repos/factorial-io/phabalicious/releases'
        );
        $releases = json_decode($payload);

        if (!is_array($releases)) {
            $this->getConfiguration()->getLogger()->warning("Could not get release information from github!");
            return false;
        }

        // Filter prereleases.
        if (!$allow_unstable) {
            $releases = array_filter($releases, function ($r) {
                return !$r->prerelease;
            });
            $releases = array_values($releases);
        }

        if (!isset($releases[0])) {
            $this->getConfiguration()->getLogger()->warning("Could not get release information from github!");
            return false;
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

            if ($output->isDecorated()
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
