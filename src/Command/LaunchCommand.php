<?php

namespace App\Command;

use Symfony\Component\Console\Input\InputInterface;

class LaunchCommand extends \App\Command {

    protected function configure()
    {
        $this->setName('launch')
            ->setDescription('Build an image')
        ;
    }

    protected function doExecute(InputInterface $input) {

        $client = $this->getClient();
        $image = $this->getImage();
        $userDataConfig = $this->get('userdata.config');
        $instanceConfig = $this->get('instance.config');
        $instanceCount = $this->get('instance.count');
        $scripts = $this->get('scripts');
        $instanceAccess = $this->getInstanceAccess();

        $instances = $client->launchInstances($image, $instanceCount, $instanceConfig, $userDataConfig);

        foreach($instances as $instance) {
            $instance->provisionWith($instanceAccess, $scripts);
        }

        $this->set('instances', $instances);

        $this->info(sprintf('Launched %s instance(s)', count($instances)));
    }
}
