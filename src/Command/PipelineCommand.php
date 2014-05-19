<?php

namespace App\Command;

use Symfony\Component\Console\Input\InputInterface;

class PipelineCommand extends \App\Command{

    protected function configure()
    {
        $this->setName('pipeline')
            ->setDescription('Execute a pipeline')
        ;
    }

    protected function doExecute(InputInterface $input) {

        $this->info('Pipeline completed');
    }
}
