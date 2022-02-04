<?php

namespace Phabalicious\Command;

use Composer\Semver\Comparator;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Utilities\Utilities;
use SelfUpdate\SelfUpdateCommand as BaseSelfUpdateCommand;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcher;

class SelfUpdateCommand extends BaseSelfUpdateCommand
{
    /**
     * @var \Phabalicious\Configuration\ConfigurationService
     */
    private $configuration;

    public function __construct(ConfigurationService $configuration, $name = null)
    {
        $this->configuration = $configuration;

        parent::__construct('phab', Utilities::FALLBACK_VERSION, 'factorial-io/phabalicious');
    }

    public function getConfiguration(): ConfigurationService
    {
        return $this->configuration;
    }

    /**
     * Get all releases from Github.
     */
    protected function getReleasesFromGithub()
    {
        $version_parser = new VersionParser();

        $payload = $this->getConfiguration()->readHttpResource(
            'https://api.github.com/repos/' . $this->gitHubRepository . '/releases'
        );
        $releases = json_decode($payload);

        if (! isset($releases[0])) {
            throw new \Exception('API error - no release found at GitHub repository ' . $this->gitHubRepository);
        }
        $parsed_releases = [];
        foreach ($releases as $release) {
            try {
                $normalized = $version_parser->normalize($release->tag_name);
            } catch (\UnexpectedValueException $e) {
                // If this version does not look quite right, let's ignore it.
                continue;
            }
            $parsed_releases[$normalized] = [
                'tag_name' => $normalized,
                'assets' => $release->assets,
            ];
        }
        $sorted_versions = Semver::rsort(array_keys($parsed_releases));
        $sorted_releases = [];
        foreach ($sorted_versions as $version) {
            $sorted_releases[$version] = $parsed_releases[$version];
        }
        return $sorted_releases;
    }

    public function isUpdateAvailable()
    {
        try {
            $version = $this->getApplication()->getVersion();
            $preview =
                (stripos($version, 'alpha') !== false) ||
                (stripos($version, 'beta') !== false);

            [$latest,] = $this->getLatestReleaseFromGithub($preview);

            $update_available = ($latest && Comparator::greaterThan($latest, $this->currentVersion));
            $this->configuration->getLogger()->debug(sprintf(
                'Version-Check: current: %s, latest on remote: %s, check for preview: %s, update available: %s',
                $version,
                $latest,
                $preview ? 'YES' : 'NO',
                $update_available ? 'YES' : 'NO'
            ));

            return $update_available
                ? [
                    'new_version' => $latest,
                    'preview' => $preview,
                ]
                : false;
        } catch (\Exception $e) {
            $this->configuration->getLogger()->warning(sprintf(
                'Could not check for updates: %s',
                $e->getMessage()
            ));
        }

        return false;
    }

    public static function registerListener(EventDispatcher $dispatcher)
    {
        $dispatcher->addListener(ConsoleEvents::COMMAND, function (ConsoleCommandEvent $event) {

            $input = $event->getInput();
            $output = $event->getOutput();

            /** @var \Phabalicious\Command\SelfUpdateCommand command */
            $command = $event->getCommand()->getApplication()->find('self-update');

            if ($output->isDecorated()
                && !$output->isQuiet()
                && !$event->getCommand()->isHidden()
                && !$event->getCommand()->getName() !== 'self:update'
                && !$command->getConfiguration()->isOffline()
                && !$input->hasParameterOption(['--offline'])
                && !$input->hasParameterOption(['--no-interaction'])
            ) {
                if ($version = $command->isUpdateAvailable()) {
                    $style = new SymfonyStyle($input, $output);
                    $style->block([
                        'Version ' . $version['new_version'] . ' of phabalicious is available. Run `phab self-update'
                        . ($version['preview'] ? ' --preview' : '')
                        . '` to update your local installation.',
                        'Visit https://github.com/factorial-io/phabalicious/releases for more info.',
                    ], null, 'fg=white;bg=blue', ' ', true);
                }
            }
        });
    }
}
