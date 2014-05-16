<?php

namespace App;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerTrait;

abstract class Task implements LoggerInterface {
    use LoggerTrait;

    /** var Psr\Log\LoggerInterface */
    protected $logger;

    /**
     * {@inheritdoc}
     */
    public function log($level, $message, array $context = array()) {
        if($this->logger) {
            $this->logger->log($level, $message, $context);
        }
    }

    public function run() {
        try {
            $thisClass = preg_replace('~^.*\\\~', '', get_class($this));
            $this->info('START TASK: ' . $thisClass);
            $response = $this->doRun();
            $this->info('COMPLETED TASK: ' . $thisClass);
            return $response;
        } catch (\Exception $e) {
            $this->error(sprintf('ERROR RUNNING TASK %s: %s', $thisClass, $e->getMessage()), ['exception' => $e,]);
            throw $e;
        }
    }

    public function __call($name, $arguments) {
        if(!preg_match('/^set([A-Z])(.*)$/', $name)) {
            throw new \BadMethodCallException(sprintf('Method does not exist: %s', $name));
        }

        $parts = explode('_', preg_replace('/([A-Z])/', '_$1', $name));
        array_shift($parts);
        $param = strtolower(implode('_', $parts));

        if(!property_exists($this, $param)) {
            throw new \OutOfBoundsException(sprintf('Param for this class does not exist: %s (method called %s)', $param, $name));
        }
        $this->$param = $arguments[0];
        return $this;
    }

    /**
     * Actually execute the task
     */
    abstract protected function doRun();
}
