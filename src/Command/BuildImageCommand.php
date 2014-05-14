<?php

namespace App\Command;

use App\AbstractCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

class BuildImageCommand extends AbstractCommand
{
    protected function configure()
    {
        $this->setName('build')
            ->setDescription('Build an image')
        ;
    }

    protected function doExecute(InputInterface $input) {
        $this->info('Build Complete');
    }
}
