<?php

namespace App\Command;

use Symfony\Component\Console\Input\InputInterface;

class LaunchCommand extends \App\TaskMasterCommand {

    protected function configure()
    {
        $this->setName('launch')
            ->setDescription('Build an image')
        ;
    }

    protected function doExecute(InputInterface $input) {

        $client = $this->get('client');

        $instances = $this->getTask('launch_instances')
            ->setClient($client)
            ->setImage($this->get('image'))
            ->setUserdataConfig($this->get('userdata_config'))
            ->setInstanceConfig($this->get('instance_config'))
            ->run();

        $this->getTask('provision_instances')
            ->setClient($client)
            ->setShellScripts($this->get('shell_scripts'))
            ->setInstance($instances)
            ->run();

        $this->info(sprintf('Launched %s instance(s)', count($instances)));
    }
}
