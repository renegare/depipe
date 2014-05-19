<?php

namespace App\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\ArrayInput;

class PipelineCommand extends \App\Command{

    protected function configure()
    {
        $this->setName('pipeline')
            ->setDescription('Execute a pipeline')
        ;
    }

    protected function doExecute(InputInterface $input) {
        $app = $this->getApplication();
        $pipes = $app->getPipeLine();
        foreach($pipes as $command => $config) {
            preg_match('/^([\w:]+)(.*)/', $command, $match);
            list($match, $commandName, $description) = $match;

            $this->info(sprintf('Running pipe \'%s\' (%s command) ...', $description, $commandName));

            $app->appendConfig($config);
            $this->runSubCommand($commandName);
        }

        $this->info('Pipeline completed', ['config' => $config]);
    }
}
