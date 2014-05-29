<?php

namespace App\Command;

use Symfony\Component\Console\Input\InputInterface;

class BuildCommand extends \App\Command {

    protected function configure()
    {
        $this->setName('build')
            ->setDescription('Build an image')
        ;
    }

    protected function doExecute(InputInterface $input) {

        $client = $this->getClient();
        $cleanUpInstances = false;
        $instances = $this->getInstances(function() use (&$cleanUpInstances){
            $this->info('There are pre-specified instances. Launching an instance to build from ...');
            $this->runSubCommand('launch');
            $cleanUpInstances = true;
            return $this->getInstances();
        });
        $imageName = $this->get('image.name');

        $image = $client->snapshotInstance($instances[0], $imageName);
        $this->set('image', $image);
        $this->info(sprintf('Built image %s', $imageName));

        if($cleanUpInstances) {
            $this->info(sprintf('Cleaning up instances: %s', implode(', ', $instances)));
            $client->killInstances($instances);
        }
    }
}
