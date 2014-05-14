<?php

namespace App;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;

abstract class AbstractCommand extends Command implements LoggerAwareInterface, LoggerInterface {
    use LoggerTrait, LoggerAwareTrait;

    private $output;

    protected function setOutput(OutputInterface $output) {
        $this->output = $output;
    }

    /**
     * {@inheritdoc}
     */
    public function log($level, $message, array $context = array()) {
        if($this->logger) {
            $this->logger->log($level, $message, $context);
        }

        if($this->output) {
            $this->output->writeln(sprintf('[%s] %s', $level, $message));
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->setOutput($output);

        try {
            $this->doExecute($input);
        } catch (\Exception $e) {
            $this->error(sprintf('Command Failed: %s', (string) $e), ['exception' => $e]);
            throw $e;
        }
    }

    public function setName($name) {
        return parent::setName('pipe:' . $name);
    }

    protected function get($key) {
        return $this->getApplication()->getConfigValue($key);
    }

    abstract protected function doExecute(InputInterface $input);
}
