<?php

namespace Phabalicious\Command;

use Composer\Semver\Comparator;
use Composer\Semver\VersionParser;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Utilities\Utilities;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcher;

#[AsCommand(name: 'self-update', description: 'Updates phabalicious to the latest version')]
class SelfUpdateCommand extends Command
{
    private const GITHUB_REPO = 'factorial-io/phabalicious';

    private ConfigurationService $configuration;

    public function __construct(ConfigurationService $configuration)
    {
        $this->configuration = $configuration;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('preview', null, InputOption::VALUE_NONE, 'Allow updating to preview (alpha/beta) releases')
            ->setHelp('
Updates phabalicious to the latest version.

This command checks GitHub for the latest release of phabalicious and updates
the local phar file to that version.

Behavior:
- Checks the factorial-io/phabalicious GitHub repository for the latest release
- Compares the current version with the latest available version
- Downloads and installs the update if a newer version is available
- Creates a backup of the current phar file before updating
- Can install preview/beta versions with the --preview flag

Examples:
<info>phab self-update</info>
<info>phab self-update --preview</info>     # Update to latest preview/beta version
            ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);

        $currentVersion = $this->getCurrentVersion();
        $preview = $input->getOption('preview');

        $style->text(sprintf('Current version: %s', $currentVersion));
        $style->text('Checking for updates...');

        $release = $this->getLatestReleaseFromGithub($preview);
        if (!$release) {
            $style->error('Could not fetch release information from GitHub.');
            return Command::FAILURE;
        }

        $latestVersion = $release['version'];
        if (!Comparator::greaterThan($latestVersion, $currentVersion)) {
            $style->success(sprintf('You are already using the latest version (%s).', $currentVersion));
            return Command::SUCCESS;
        }

        $style->text(sprintf('Updating to version %s...', $latestVersion));

        $pharPath = \Phar::running(false);
        if (empty($pharPath)) {
            $style->error('Self-update is only available when running as a PHAR.');
            return Command::FAILURE;
        }

        $downloadUrl = $release['download_url'] ?? null;
        if (!$downloadUrl) {
            $style->error('No download URL found for the latest release.');
            return Command::FAILURE;
        }

        // Create backup.
        $backupPath = $pharPath . '.backup';
        if (!copy($pharPath, $backupPath)) {
            $style->error('Could not create backup of current phar.');
            return Command::FAILURE;
        }

        // Download new version.
        $tempPath = $pharPath . '.tmp';
        $context = stream_context_create([
            'http' => [
                'header' => "User-Agent: phabalicious\r\n",
            ],
        ]);

        $newPhar = @file_get_contents($downloadUrl, false, $context);
        if ($newPhar === false) {
            $style->error('Could not download the new version.');
            @unlink($backupPath);
            return Command::FAILURE;
        }

        if (file_put_contents($tempPath, $newPhar) === false) {
            $style->error('Could not write the new version.');
            @unlink($backupPath);
            return Command::FAILURE;
        }

        // Replace current phar.
        if (!rename($tempPath, $pharPath)) {
            $style->error('Could not replace the current phar. Restoring backup...');
            rename($backupPath, $pharPath);
            return Command::FAILURE;
        }

        chmod($pharPath, 0755);
        @unlink($backupPath);

        $style->success(sprintf('Updated from %s to %s.', $currentVersion, $latestVersion));

        return Command::SUCCESS;
    }

    public function getConfiguration(): ConfigurationService
    {
        return $this->configuration;
    }

    public function isUpdateAvailable(): bool|array
    {
        try {
            $version = $this->getCurrentVersion();
            $preview =
                false !== stripos($version, 'alpha')
                || false !== stripos($version, 'beta');

            $release = $this->getLatestReleaseFromGithub($preview);
            if (!$release) {
                return false;
            }

            $latest = $release['version'];
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

    private function getCurrentVersion(): string
    {
        $version_parser = new VersionParser();
        $version = $this->getApplication()
            ? $this->getApplication()->getVersion()
            : Utilities::FALLBACK_VERSION;

        return $version_parser->normalize($version);
    }

    private function getLatestReleaseFromGithub(bool $preview = false): ?array
    {
        $url = sprintf('https://api.github.com/repos/%s/releases', self::GITHUB_REPO);
        $context = stream_context_create([
            'http' => [
                'header' => "User-Agent: phabalicious\r\nAccept: application/vnd.github.v3+json\r\n",
                'timeout' => 10,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            return null;
        }

        $releases = json_decode($response, true);
        if (!is_array($releases)) {
            return null;
        }

        $version_parser = new VersionParser();
        foreach ($releases as $release) {
            if (!$preview && ($release['prerelease'] ?? false)) {
                continue;
            }

            $tag = $release['tag_name'] ?? null;
            if (!$tag) {
                continue;
            }

            try {
                $version = $version_parser->normalize($tag);
            } catch (\Exception $e) {
                continue;
            }

            // Find phar asset.
            $downloadUrl = null;
            foreach ($release['assets'] ?? [] as $asset) {
                if (str_ends_with($asset['name'] ?? '', '.phar')) {
                    $downloadUrl = $asset['browser_download_url'] ?? null;
                    break;
                }
            }

            return [
                'version' => $version,
                'download_url' => $downloadUrl,
            ];
        }

        return null;
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
                && 'self-update' !== $event->getCommand()->getName()
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
