<?php

namespace Phabalicious\Command;

use Composer\Semver\Comparator;
use Composer\Semver\VersionParser;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Utilities\Utilities;
use SelfUpdate\SelfUpdateCommand as BaseSelfUpdateCommand;
use SelfUpdate\SelfUpdateManager;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcher;

class SelfUpdateCommand extends BaseSelfUpdateCommand
{
    private ConfigurationService $configuration;
    private SelfUpdateManager $selfUpdateManager;

    public function __construct(ConfigurationService $configuration)
    {
        $this->configuration = $configuration;

        $version_parser = new VersionParser();
        $version = $version_parser->normalize(Utilities::FALLBACK_VERSION);

        $this->selfUpdateManager = new SelfUpdateManager(
            'phab',
            $version,
            'factorial-io/phabalicious'
        );

        parent::__construct($this->selfUpdateManager);
    }

    public function getConfiguration(): ConfigurationService
    {
        return $this->configuration;
    }

    public function isUpdateAvailable(): bool|array
    {
        try {
            $version = $this->getApplication()->getVersion();
            $version_parser = new VersionParser();
            $version = $version_parser->normalize($version);
            $preview =
                false !== stripos($version, 'alpha')
                || false !== stripos($version, 'beta');

            $latest = $this->selfUpdateManager->getLatestReleaseFromGithub([
                'preview' => $preview,
            ])['version'];

            $update_available =
                $latest && Comparator::greaterThan($latest, $version);
            $this->configuration
                ->getLogger()
                ->debug(
                    sprintf(
                        'Version-Check: current: %s, latest on remote: %s, check for preview: %s, update available: %s',
                        $version,
                        $latest,
                        $preview ? 'YES' : 'NO',
                        $update_available ? 'YES' : 'NO'
                    )
                );

            return $update_available
                ? [
                    'new_version' => $latest,
                    'preview' => $preview,
                ]
                : false;
        } catch (\Exception $e) {
            $this->configuration
                ->getLogger()
                ->warning(
                    sprintf('Could not check for updates: %s', $e->getMessage())
                );
        }

        return false;
    }

    public static function registerListener(EventDispatcher $dispatcher): void
    {
        $dispatcher->addListener(ConsoleEvents::COMMAND, function (
            ConsoleCommandEvent $event,
        ) {
            $input = $event->getInput();
            $output = $event->getOutput();

            /** @var SelfUpdateCommand $command */
            $command = $event
                ->getCommand()
                ->getApplication()
                ->find('self-update');

            if ($output->isDecorated()
                && !$output->isQuiet()
                && !$event->getCommand()->isHidden()
                && 'self:update' !== $event->getCommand()->getName()
                && !$command->getConfiguration()->isOffline()
                && !$input->hasParameterOption(['--offline'])
                && !$input->hasParameterOption(['--no-interaction'])
                && ($version = $command->isUpdateAvailable())
            ) {
                $style = new SymfonyStyle($input, $output);
                $style->block(
                    [
                        'Version '.
                        $version['new_version'].
                        ' of phabalicious is available. Run `phab self-update'.
                        ($version['preview'] ? ' --preview' : '').
                        '` to update your local installation.',
                        'Visit https://github.com/factorial-io/phabalicious/releases for more info.',
                    ],
                    null,
                    'fg=white;bg=blue',
                    ' ',
                    true
                );
            }
        });
    }
}
