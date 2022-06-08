<?php

namespace Phabalicious\Utilities;

use Psr\Log\LogLevel;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Logger extends ConsoleLogger
{
    const WARNING = 'warning';
    const NOTICE = 'notice';
    const INFO = 'info';
    const DEBUG = 'debug';

    private $io;
    private $output;

    /**
     * Overrides $verbosityLevelMap in the parent class.
     *
     * The property name is changed to prevent a crash caused by a bug in PHP
     * 8.0.8: see https://github.com/factorial-io/phabalicious/issues/272.
     *
     * @var array
     */
    private $verbosityLevelMapOverride = array(
        LogLevel::EMERGENCY => OutputInterface::VERBOSITY_NORMAL,
        LogLevel::ALERT => OutputInterface::VERBOSITY_NORMAL,
        LogLevel::CRITICAL => OutputInterface::VERBOSITY_NORMAL,
        LogLevel::ERROR => OutputInterface::VERBOSITY_NORMAL,
        LogLevel::WARNING => OutputInterface::VERBOSITY_NORMAL,
        LogLevel::NOTICE => OutputInterface::VERBOSITY_VERBOSE,
        LogLevel::INFO => OutputInterface::VERBOSITY_VERY_VERBOSE,
        LogLevel::DEBUG => OutputInterface::VERBOSITY_DEBUG,
    );

    public function __construct(InputInterface $input, OutputInterface $output)
    {
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

    /**
     * {@inheritdoc}
     */
    public function log($level, $message, array $context = array())
    {
        if ($this->output->getVerbosity() < $this->verbosityLevelMapOverride[$level]) {
            return;
        }
        if ($level == LogLevel::WARNING) {
            $this->io->block($message, 'Warning', 'fg=black;bg=yellow', '   ', true);
        } elseif ($level == LogLevel::ERROR) {
            $this->io->error($message);
        } else {
            parent::log($level, $message, $context);
        }
    }
}
