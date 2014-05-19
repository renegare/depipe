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
        $instances = $this->getSubCommandValue('launch', 'instances');
        $imageName = $this->get('image_name');

        $image = $client->snapshotInstance($instances[0], $imageName);
        $this->set('image', $image);

        $this->info(sprintf('Built image %s', $imageName));
    }
}
