<?php

namespace App\Command;

use Symfony\Component\Console\Input\InputInterface;

class FindImageCommand extends \App\Command {

    protected function configure()
    {
        $this->setName('find:image')
            ->setDescription('find image that matches user defined name (config \'image.name\')')
        ;
    }

    protected function doExecute(InputInterface $input) {

        $client = $this->getClient();
        $imageName = $this->get('image.name');
        $image = $client->findImage($imageName);
        if($image) {
            $this->set('image', $image);
        }

        $this->info(sprintf('Found image \'%s\'', $imageName));
    }
}
