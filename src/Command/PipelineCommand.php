<?php

namespace App\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\ArrayInput;

class PipelineCommand extends \App\Command{

    protected $configHistory;

    protected function configure()
    {
        $this->setName('pipeline')
            ->setDescription('Execute a pipeline')
        ;
    }

    protected function doExecute(InputInterface $input) {
        $app = $this->getApplication();
        $pipes = $app->getPipeLine();
        foreach($pipes as $pipeName => $config) {
            preg_match('/^([\w:]+)(.*)/', $pipeName, $match);
            list($match, $commandName, $description) = $match;

            $config = $this->processConfig($config);
            $this->info(sprintf('Running pipe \'%s\' ...', $pipeName));

            $app->appendConfig($config);
            $this->runSubCommand($commandName);
            $this->configHistory[$pipeName] = $app->getConfig();
        }

        $this->info('Pipeline completed');
    }

    protected function processConfig(array $config) {
        foreach($config as $key => $value) {
            if(preg_match('/^@/', $key)) {
                switch($key) {
                    case '@from':
                        $config = $this->getConfigFrom($value, $config);
                        break;
                    default:
                        throw new \InvalidArgumentException(sprintf('Unexpected %s parameter', $key));

                }

                unset($config[$key]);
            }
        }
        return $config;
    }

    protected function getConfigFrom($pipeName, $topConfig) {
        return array_merge($this->configHistory[$pipeName], $topConfig);
    }
}
