<?php

namespace App\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Aws\Ec2\Ec2Client;
use Symfony\Component\Yaml\Dumper;

class BuildImageCommand extends TaskMasterCommand
{
    protected $ec2Client;

    protected function configure()
    {
        $this->setName('build')
            ->setDescription('Build an image')
        ;
    }

    protected function doExecute(InputInterface $input) {

        $client = $this->getTask('get_client')
            ->setCredentials($this->get('credentials'))
            ->run();

        $this->info('Build Complete');
    }
}
