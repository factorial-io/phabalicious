<?php

namespace Phabalicious\Command;

use Composer\Semver\Comparator;
use Composer\Semver\VersionParser;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Utilities\Utilities;
use SelfUpdate\SelfUpdateCommand as BaseSelfUpdateCommand;
use SelfUpdate\SelfUpdateManager;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcher;

class SelfUpdateCommand extends BaseSelfUpdateCommand
{
    /**
     * @var \Phabalicious\Configuration\ConfigurationService
     */
    private ConfigurationService $configuration;
    private SelfUpdateManager $selfUpdateManager;

    public function __construct(ConfigurationService $configuration)
    {
        $this->configuration = $configuration;

        $version_parser = new VersionParser();
        $version = $version_parser->normalize(Utilities::FALLBACK_VERSION);

        $this->selfUpdateManager = new SelfUpdateManager(
            "phab",
            $version,
            "factorial-io/phabalicious"
        );

        parent::__construct($this->selfUpdateManager);
    }

    protected function configure(): void
    {
        parent::configure();
        $this->setHelp('
Updates phabalicious to the latest version.

This command checks GitHub for the latest release of phabalicious and updates
the local phar file to that version. It uses the consolidation/self-update
library to safely download and install updates.

Behavior:
- Checks the factorial-io/phabalicious GitHub repository for the latest release
- Compares the current version with the latest available version
- Downloads and installs the update if a newer version is available
- Creates a backup of the current phar file before updating
- Can install preview/beta versions with the --preview flag

The command automatically detects if you are running a preview version
(alpha/beta) and will check for preview updates accordingly.

Phabalicious also shows update notifications when a new version is available
(unless running with --offline or --no-interaction).

Examples:
<info>phab self-update</info>
<info>phab self-update --preview</info>     # Update to latest preview/beta version
            ');
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
                stripos($version, "alpha") !== false ||
                stripos($version, "beta") !== false;

            $latest = $this->selfUpdateManager->getLatestReleaseFromGithub([
                "preview" => $preview,
            ])["version"];

            $update_available =
                $latest && Comparator::greaterThan($latest, $version);
            $this->configuration
                ->getLogger()
                ->debug(
                    sprintf(
                        "Version-Check: current: %s, latest on remote: %s, check for preview: %s, update available: %s",
                        $version,
                        $latest,
                        $preview ? "YES" : "NO",
                        $update_available ? "YES" : "NO"
                    )
                );

            return $update_available
                ? [
                    "new_version" => $latest,
                    "preview" => $preview,
                ]
                : false;
        } catch (\Exception $e) {
            $this->configuration
                ->getLogger()
                ->warning(
                    sprintf("Could not check for updates: %s", $e->getMessage())
                );
        }

        return false;
    }

    public static function registerListener(EventDispatcher $dispatcher): void
    {
        $dispatcher->addListener(ConsoleEvents::COMMAND, function (
            ConsoleCommandEvent $event
        ) {
            $input = $event->getInput();
            $output = $event->getOutput();

            /** @var \Phabalicious\Command\SelfUpdateCommand $command */
            $command = $event
                ->getCommand()
                ->getApplication()
                ->find("self-update");

            if ($output->isDecorated() &&
                !$output->isQuiet() &&
                !$event->getCommand()->isHidden() &&
                $event->getCommand()->getName() !== "self:update" &&
                !$command->getConfiguration()->isOffline() &&
                !$input->hasParameterOption(["--offline"]) &&
                !$input->hasParameterOption(["--no-interaction"]) &&
                ($version = $command->isUpdateAvailable())
            ) {
                $style = new SymfonyStyle($input, $output);
                $style->block(
                    [
                        "Version " .
                        $version["new_version"] .
                        " of phabalicious is available. Run `phab self-update" .
                        ($version["preview"] ? " --preview" : "") .
                        "` to update your local installation.",
                        "Visit https://github.com/factorial-io/phabalicious/releases for more info.",
                    ],
                    null,
                    "fg=white;bg=blue",
                    " ",
                    true
                );
            }
        });
    }
}
