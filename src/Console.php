<?php

namespace App;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Symfony\Component\Yaml\Parser;

class Console extends Application {

    protected $config = [];
    protected $logger;

    public function setConfig(array $config) {
        $this->config = $config;
    }

    public function getConfig() {
        return $this->config;
    }

    public function getConfigValue($key, $default = null) {
        if(!isset($this->config[$key]) && $default !== null) {
            if($default instanceof \Closure) {
                $default = $default();
            }
            $this->setConfigValue($key, $default);
        }

        return $this->config[$key];
    }

    public function setConfigValue($key, $value) {
        $this->config[$key] = $value;
    }

    protected function getDefaultInputDefinition() {
        $definitions = parent::getDefaultInputDefinition();
        $definitions->addOption(new InputOption('--log', '-l', InputOption::VALUE_REQUIRED, 'Log out put to this file path'));
        $definitions->addOption(new InputOption('--config', '-c', InputOption::VALUE_REQUIRED, 'YAML config file path'));
        return $definitions;
    }

    protected function doRunCommand(Command $command, InputInterface $input, OutputInterface $output) {

        if($input->hasParameterOption(['--log', '-l']) && $command instanceof \App\Command) {
            $logPath = $input->getParameterOption(['--log', '-l']);

            $log = new Logger('depipe');
            $log->pushHandler(new StreamHandler($logPath));

            $command->setLogger($log);
        }

        parent::doRunCommand($command, $input, $output);
    }

    protected function configureIO(InputInterface $input, OutputInterface $output) {

        if($input->hasParameterOption(['--config', '-c'])) {
            $configPath = $input->getParameterOption(['--config', '-c']);
            $yaml = new Parser();
            $config = $this->processPlaceHolders(file_get_contents($configPath));
            $config = $yaml->parse($config);
            $params = isset($config['parameters'])? $config['parameters'] : [];
            $this->setConfig($params);
        }

        return parent::configureIO($input, $output);
    }

    protected function processPlaceHolders($config) {
        return preg_replace_callback('/{{\s*(\w+)\s+([^}]+)}}/', function($matches) {
            $source = $matches[1];
            $arg = trim($matches[2]);

            switch($source) {
                case 'env':
                    return getenv($arg);
                default:
                    throw new \BadFunctionCallException('Invalid place holder ' . $source);
            }
        }, $config);
    }
}
