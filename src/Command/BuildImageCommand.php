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

        $client = $this->get('client');
        $instances = $this->get('instances', function(){
            $this->runSubCommand('launch');
            return $this->get('instances');
        });

        $imageName = $this->get('image_name');

        $image = $this->getTask('snapshot_instance')
            ->setClient($client)
            ->setImageName($imageName)
            ->setInstance($instances[0])
            ->run();

        $this->set('image', $image);

        $this->info(sprintf('Built image %s', $imageName));
    }
}
