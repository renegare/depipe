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
        $imageName = $this->get('image.name');

        try {
            $image = $this->getImage(function(){
                $this->info('There is NO pre-specified image. Searching for a matching image ...');
                $this->runSubCommand('find:image');
                return $this->getImage();
            });
            
            $this->info(sprintf('Image \'%s\' has already been built.', $imageName));
        } catch(\Exception $e) {

            $client = $this->getClient();
            $cleanUpInstances = false;
            $instances = $this->getInstances(function() use (&$cleanUpInstances){
                $this->info('There are NO pre-specified instances. Launching an instance to build from ...');
                $this->runSubCommand('launch');
                $cleanUpInstances = true;
                return $this->getInstances();
            });

            $image = $client->snapshotInstance($instances[0], $imageName);
            $this->info(sprintf('Built image %s', $imageName));

            if($cleanUpInstances) {
                $this->info(sprintf('Cleaning up instances: %s', implode(', ', $instances)));
                $client->killInstances($instances);
                $this->set('instances', null);
            }
        }

        $this->set('image', $image);
        $this->info('Image Build Complete');
    }
}
