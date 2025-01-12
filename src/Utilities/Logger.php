<?php

namespace Phabalicious\Utilities;

use Psr\Log\LogLevel;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Logger extends ConsoleLogger
{
    public const WARNING = 'warning';
    public const NOTICE = 'notice';
    public const INFO = 'info';
    public const DEBUG = 'debug';

    private SymfonyStyle $io;
    private OutputInterface $output;

    protected ?PasswordManagerInterface $passwordManager = null;

    /**
     * Overrides $verbosityLevelMap in the parent class.
     *
     * The property name is changed to prevent a crash caused by a bug in PHP
     * 8.0.8: see https://github.com/factorial-io/phabalicious/issues/272.
     */
    private array $verbosityLevelMapOverride = [
        LogLevel::EMERGENCY => OutputInterface::VERBOSITY_NORMAL,
        LogLevel::ALERT => OutputInterface::VERBOSITY_NORMAL,
        LogLevel::CRITICAL => OutputInterface::VERBOSITY_NORMAL,
        LogLevel::ERROR => OutputInterface::VERBOSITY_NORMAL,
        LogLevel::WARNING => OutputInterface::VERBOSITY_NORMAL,
        LogLevel::NOTICE => OutputInterface::VERBOSITY_VERBOSE,
        LogLevel::INFO => OutputInterface::VERBOSITY_VERY_VERBOSE,
        LogLevel::DEBUG => OutputInterface::VERBOSITY_DEBUG,
    ];

    public function __construct(InputInterface $input, OutputInterface $output)
    {
        if (!$output->isDecorated()) {
            // For undecorated output warnings will be shown only when using verbose mode.
            $this->verbosityLevelMapOverride[LogLevel::WARNING] = OutputInterface::VERBOSITY_VERBOSE;

            if ($output instanceof ConsoleOutputInterface) {
                $output = $output->getErrorOutput();
            }
        }

        $output->getFormatter()->setStyle(
            self::WARNING,
            new OutputFormatterStyle('yellow', 'default')
        );
        $output->getFormatter()->setStyle(
            self::NOTICE,
            new OutputFormatterStyle('green', 'default')
        );
        $output->getFormatter()->setStyle(
            self::INFO,
            new OutputFormatterStyle('cyan', 'default')
        );
        $output->getFormatter()->setStyle(
            self::DEBUG,
            new OutputFormatterStyle('blue', 'default')
        );

        $formatLevelMap = [
            LogLevel::EMERGENCY => self::ERROR,
            LogLevel::ALERT => self::ERROR,
            LogLevel::CRITICAL => self::ERROR,
            LogLevel::ERROR => self::ERROR,
            LogLevel::WARNING => self::WARNING,
            LogLevel::NOTICE => self::NOTICE,
            LogLevel::INFO => self::INFO,
            LogLevel::DEBUG => self::DEBUG,
        ];
        if (!$output->isDecorated()) {
            // For undecorated output warnings will be shown only when using verbose mode.
            $this->verbosityLevelMapOverride[LogLevel::WARNING] = OutputInterface::VERBOSITY_VERBOSE;
        }

        parent::__construct(
            $output,
            $this->verbosityLevelMapOverride,
            $formatLevelMap
        );

        $this->io = new SymfonyStyle($input, $output);
        $this->output = $output;
    }

    public function log($level, $message, array $context = []): void
    {
        if ($this->output->getVerbosity() < $this->verbosityLevelMapOverride[$level]) {
            return;
        }
        if ($this->passwordManager) {
            $message = $this->passwordManager->obfuscateSecrets($message);
        }
        if (LogLevel::WARNING === $level) {
            $this->io->block($message, 'Warning', 'fg=black;bg=yellow', '   ', true);
        } elseif (LogLevel::ERROR === $level) {
            $this->io->error($message);
        } else {
            parent::log($level, $message, $context);
        }
    }

    public function setPasswordManager(PasswordManagerInterface $passwordManager): Logger
    {
        $this->passwordManager = $passwordManager;

        return $this;
    }
}
