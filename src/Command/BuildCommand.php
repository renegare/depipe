<?php

namespace App\Command;

use Symfony\Component\Console\Input\InputInterface;

class BuildCommand extends \App\TaskMasterCommand {

    protected function configure()
    {
        $this->setName('build')
            ->setDescription('Build an image')
        ;
    }

    protected function doExecute(InputInterface $input) {

        $client = $this->get('client');
        $instances = $this->getSubCommandValue('launch', 'instances');
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
