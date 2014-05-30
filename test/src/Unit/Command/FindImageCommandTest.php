<?php

namespace App\Test\Command;

use App\Test\Util\ConsoleTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class FindImageCommandTest extends ConsoleTestCase {

    /**
     * Assert that the command attempts to find a image with user defined name
     * (config 'image.name')
     */
    public function testExecution() {

        $app = $this->getApplication();
        $command = $app->find('find:image');
        $this->assertInstanceOf('App\Command\FindImageCommand', $command);
        $mockImage = $this->getMock('App\Platform\ImageInterface');
        $mockClient = $this->getMock('App\Platform\ClientInterface');
        $mockClient->expects($this->once())
            ->method('findImage')
            ->will($this->returnCallback(function($imageName) use ($mockImage){
                $this->assertEquals('Test Image Name', $imageName);
                return $mockImage;
            }));
        $app->setClient($mockClient);

        $app->setConfig(['image.name' => 'Test Image Name']);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertContains('Found image \'Test Image Name\'', $commandTester->getDisplay());

        $this->assertEquals([
            'image.name' => 'Test Image Name',
            'image' => $mockImage
        ], $app->getConfig());
        return;

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
