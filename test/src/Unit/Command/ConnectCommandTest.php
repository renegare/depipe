<?php

namespace App\Test\Command;

use App\Test\Util\ConsoleTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ConnectCommandTest extends ConsoleTestCase {

    /**
     * Assert that the command connects all instances to a load balancer
     * - Given I have [instance(s)]
     * - AND I have a client
     * - AND I have a loadbalancer
     *
     * - RUN connect_to_loadbalancer task: [client, [instance(s)], loadbalancer]
     */
    public function testExecution() {

        $app = $this->getApplication();
        $command = $app->find('connect');
        $this->assertInstanceOf('App\Command\ConnectCommand', $command);

        $mockInstances = [$this->getMock('App\Platform\InstanceInterface'), $this->getMock('App\Platform\InstanceInterface')];
        $mockLoadBalancer = $this->getMock('App\Platform\LoadBalancerInterface');

        $expectedConfig = [
            'instances' => $mockInstances,
            'load.balancer' => 'load-balancer-identifier'
        ];

        $app->setConfig($expectedConfig);

        $mockClient = $this->getMock('App\Platform\ClientInterface');
        $mockClient->expects($this->once())
            ->method('connectInstancesToLoadBalancer')
            ->will($this->returnCallback(function($instances, $loadBalancer) use ($mockLoadBalancer, $mockInstances){
                $this->assertEquals($instances, $mockInstances);
                $this->assertEquals($loadBalancer, $mockLoadBalancer);
            }));
        $mockClient->expects($this->once())
            ->method('convertToInstances')
            ->will($this->returnValue($mockInstances));

        $mockClient->expects($this->once())
            ->method('convertToLoadBalancer')
            ->will($this->returnCallback(function($loadBalancerName) use ($expectedConfig, $mockLoadBalancer){
                $this->assertEquals($expectedConfig['load.balancer'], $loadBalancerName);
                return $mockLoadBalancer;
            }));
        $app->setClient($mockClient);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertContains('Connected 2 instance(s) to load balancer', $commandTester->getDisplay());
    }
}
