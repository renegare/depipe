<?php

namespace App;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Parser;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

use App\Platform\ClientInterface;
use App\Platform\InstanceAccessInterface;
use App\Platform\LoadBalancerInterface;
use App\Platform\ImageInterface;

use App\Util\InstanceAccess\SSHAccess;

class Console extends Application {

    protected $config = [];
    protected $logger;
    protected $client;
    protected $instanceAccess;
    protected $pipeline = [];

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
            $logDir = realpath(dirname($logPath));

            if(!$logDir) {
                throw new \InvalidArgumentException(sprintf('Log folder does not exist! %s', $logDir));
            }

            $log = new Logger('depipe');
            $log->pushHandler(new StreamHandler($logPath));
            $command->setLogger($log);
            $this->logger = $command;
        }

        parent::doRunCommand($command, $input, $output);
    }

    protected function configureIO(InputInterface $input, OutputInterface $output) {

        if($input->hasParameterOption(['--config', '-c'])) {
            $configPath = realpath($input->getParameterOption(['--config', '-c']));
            if(!$configPath) {
                throw new \InvalidArgumentException(sprintf('Config file does not exists! %s', $configPath));
            }
            $cwd = getcwd();

            chdir(dirname($configPath));

            $yaml = new Parser();
            $config = file_get_contents($configPath);
            $config = $yaml->parse($config);

            array_walk_recursive($config, function(&$item, $key){
                $item = $this->processPlaceHolder($item);
            });

            $params = isset($config['parameters'])? $config['parameters'] : [];
            $pipeline = isset($config['pipeline'])? $config['pipeline'] : [];
            $this->setConfig($params);
            $this->setPipeLine($pipeline);

            chdir($cwd);
        }

        return parent::configureIO($input, $output);
    }

    /**
     * processing place holders in config using text replacement.
     * @todo serious refactoring as it breaks every rule of DRY OOP.
     * @param string $config
     * @param string - hopefully valid yaml!
     */
    public function processPlaceHolders($config) {

        $config = preg_replace_callback('/[\'"]{{\s*(\w+)\s+([^}]+)}}[\'"]/', [$this, 'processPlaceHolder'], $config);

        $config = preg_replace_callback('/{{\s*(\w+)\s+([^}]+)}}/', [$this, 'processPlaceHolder'], $config);

        return $config;
    }

    public function processPlaceHolder($value) {
        $pattern = '/{{\s*(\w+)(:?\s+([^}]+))?}}/';
        $count = preg_match_all($pattern, $value);

        if($count < 1) {
            return $value;
        }

        if($count > 1) {
            throw new \RuntimeException(sprintf("You can only have one placeholder per param: '%s'", $value));
        }

        preg_match($pattern, $value, $matches);

        $source = $matches[1];
        $arg = isset($matches[2])? trim($matches[2]) : null;

        switch($source) {
            case 'env':
                return preg_replace($pattern, getenv($arg), $value);
            case 'file':
                $arg = file_get_contents($arg);
                $arg = preg_replace('/\\\/', '\\\\\\\\', $arg);
                $arg = preg_replace('/\$/', '\\\$', $arg);
                return preg_replace($pattern, $arg, $value);
            case 'time':
                return preg_replace($pattern, $arg ? date($arg) : time(), $value);
            default:
                throw new \BadFunctionCallException('Invalid place holder ' . $source);
        }
    }

    public function getPipeline() {
        return $this->pipeline;
    }

    public function setPipeline($config) {
        return $this->pipeline = $config;
    }

    public function appendConfig(array $config) {
        $this->config = array_merge($this->config, $config);
    }

    public function setClient(ClientInterface $client) {
        if($this->logger) {
            $client->setLogger($this->logger);
        }
        $this->client = $client;
    }

    public function getClient() {
        if(!$this->client) {
            $crendtials = $this->getConfigValue('credentials');
            $class = $crendtials['vendor'];
            $client = new $class;
            if($this->logger) {
                $client->setLogger($this->logger);
            }
            $client->setCredentials($crendtials);
            $this->client = $client;
        }
        return $this->client;
    }

    public function setInstanceAccess(InstanceAccessInterface $instanceAccess) {
        $this->instanceAccess = $instanceAccess;
        if($this->logger) {
            $this->instanceAccess->setLogger($this->logger);
        }
    }

    public function getInstanceAccess() {
        if(!$this->instanceAccess) {
            $this->instanceAccess = new SSHAccess();
            if($this->logger) {
                $this->instanceAccess->setLogger($this->logger);
            }
        }

        $this->instanceAccess->setCredentials($this->getConfigValue('instance.access'));
        return $this->instanceAccess;
    }

    public function getImage($default=null) {
        $image = $this->getConfigValue('image', $default);
        if(!($image instanceof ImageInterface)) {
            $image = $this->getClient()
                ->convertToImage($image);
        }
        return $image;
    }

    public function getInstances($default=null) {
        return $this->getClient()
            ->convertToInstances($this->getConfigValue('instances', $default));
    }

    public function getLoadBalancer($default=null) {
        $loadBalancer = $this->getConfigValue('load.balancer', $default);
        if(!($loadBalancer instanceof LoadBalancerInterface)) {
            return $this->getClient()
                ->convertToLoadBalancer($loadBalancer);
        }

        return $loadBalancer;
    }
}
