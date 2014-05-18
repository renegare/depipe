<?php

namespace App\Command;

use Symfony\Component\Console\Input\InputInterface;

class ConnectCommand extends \App\TaskMasterCommand {

    protected function configure()
    {
        $this->setName('connect')
            ->setDescription('Connect intance(s) to load balancer')
        ;
    }

    protected function doExecute(InputInterface $input) {

        $client = $this->get('client');
        $loadBalancer = $this->get('load_balancer');
        $instances = $this->getSubCommandValue('launch', 'instances');

        $this->getTask('connect_to_loadbalancer')
            ->setClient($client)
            ->setLoadBalancer($loadBalancer)
            ->setInstances($instances)
            ->run();

        $this->info(sprintf('Connected %s instance(s) to load balancer \'%s\'', count($instances), $loadBalancer));
    }
}
