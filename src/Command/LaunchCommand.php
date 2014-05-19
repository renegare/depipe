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

        $client = $this->getClient();
        $image = $this->get('image');
        $userDataConfig = $this->get('userdata_config');
        $instanceConfig = $this->get('instance_config');
        $instanceCount = $this->get('instance_count');
        $shellScripts = $this->get('shell_scripts');

        $instances = $client->launchInstances($image, $instanceCount, $instanceConfig, $userDataConfig);

        $client->provisionInstances($instances, $shellScripts);

        $this->set('instances', $instances);

        $this->info(sprintf('Launched %s instance(s)', count($instances)));
    }
}
