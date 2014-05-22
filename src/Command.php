<?php

namespace App;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Input\ArrayInput;

abstract class Command extends BaseCommand implements LoggerAwareInterface, LoggerInterface {
    use LoggerTrait, LoggerAwareTrait;

    private $output;

    public function setOutput(OutputInterface $output) {
        $this->output = $output;
    }

    public function getOutput() {
        return $this->output;
    }

    /**
     * {@inheritdoc}
     */
    public function log($level, $message, array $context = array()) {
        if($this->logger) {
            $this->logger->log($level, $message, $context);
        }

        if($this->output) {
            $allowedWriteLevels = [];
            $verbosity = $this->output->getVerbosity();

            if($verbosity >= OutputInterface::VERBOSITY_NORMAL) {
                $allowedWriteLevels[] = LogLevel::INFO;
                $allowedWriteLevels[] = LogLevel::CRITICAL;
                $allowedWriteLevels[] = LogLevel::ERROR;
            }

            if($verbosity >= OutputInterface::VERBOSITY_VERBOSE) {
                $allowedWriteLevels[] = LogLevel::NOTICE;
                $allowedWriteLevels[] = LogLevel::WARNING;
            }

            if($verbosity >= OutputInterface::VERBOSITY_VERY_VERBOSE) {
                $allowedWriteLevels[] = LogLevel::ALERT;
                $allowedWriteLevels[] = LogLevel::EMERGENCY;
            }

            if($verbosity >= OutputInterface::VERBOSITY_DEBUG) {
                $allowedWriteLevels[] = LogLevel::DEBUG;
            }

            if(in_array($level, $allowedWriteLevels)) {
                $this->output->writeln(sprintf('[%s] %s', $level, $message));
            }
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->setOutput($output);

        try {
            $this->doExecute($input);
        } catch (\Exception $e) {
            $this->error(sprintf('Command Failed: %s', $e->getMessage()), ['exception' => $e]);
            throw $e;
        }
    }

    protected function runSubCommand($name) {
        $this->info('running subcommand: ' . $name);
        $command = $this->getApplication()
            ->find($name);
        $input = new ArrayInput(['command' => $name]);
        $command->run($input, $this->getOutput());
    }

    public function get($key, $default = null) {
        return $this->getApplication()
            ->getConfigValue($key, $default);
    }

    public function set($key, $value) {
        return $this->getApplication()
            ->setConfigValue($key, $value);
    }

    protected function getSubCommandValue($subCommand, $key) {
        return $this->get('instances', function(){
            $this->runSubCommand('launch');
            return $this->get('instances');
        });
    }

    protected function getClient() {
        return $this->getApplication()->getClient();
    }

    protected function getInstanceAccess() {
        return $this->getApplication()->getInstanceAccess();
    }

    protected function getImage() {
        return $this->getApplication()->getImage();
    }

    abstract protected function doExecute(InputInterface $input);
}
