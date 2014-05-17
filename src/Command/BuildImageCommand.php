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

        $instance = $this->getTask('launch_instance')
            ->setClient($client)
            ->setBaseImage($this->get('base_image'))
            ->setUserdataConfig($this->get('userdata_config'))
            ->setInstanceConfig($this->get('instance_config'))
            ->run();

        $this->getTask('provision_instance')
            ->setClient($client)
            ->setShellScripts($this->get('shell_scripts'))
            ->setInstance($instance)
            ->run();

        $image = $this->getTask('snapshot_instance')
            ->setClient($client)
            ->setNewImage($this->get('new_image'))
            ->setInstance($instance)
            ->run();

        $this->getTask('terminate_instance')
            ->setClient($client)
            ->setInstance($instance)
            ->run();

        $artifacts = $this->getTask('get_artifacts')
            ->setImage($image)
            ->run();

        $this->info('Build Complete');
    }
}
