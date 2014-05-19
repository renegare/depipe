<?php

namespace App\Command;

use Symfony\Component\Console\Input\InputInterface;

class ConnectCommand extends \App\Command {

    protected function configure()
    {
        $this->setName('connect')
            ->setDescription('Connect intance(s) to load balancer')
        ;
    }

    protected function doExecute(InputInterface $input) {

        $client = $this->getClient();
        $loadBalancer = $this->get('load_balancer');
        $instances = $this->getSubCommandValue('launch', 'instances');

        $client->connectInstancesToLoadBalancer($instances, $loadBalancer);

        $this->info(sprintf('Connected %s instance(s) to load balancer', count($instances)));
    }
}
