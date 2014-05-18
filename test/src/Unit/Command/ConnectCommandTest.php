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

        $mockInstances = [$this->getMock('App\Instance'), $this->getMock('App\Instance')];
        $mockClient = $this->getMock('App\Client');

        $expectedConfig = [
            'instances' => $mockInstances,
            'client' => $mockClient,
            'load_balancer' => 'load-balancer-identifier'
        ];

        $app->setConfig($expectedConfig);

        $this->mockTask('connect_to_loadbalancer', $command, [
            'client' => $mockClient,
            'instances' => $mockInstances,
            'load_balancer' => 'load-balancer-identifier']);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertContains('Connected 2 instance(s) to load balancer \'load-balancer-identifier\'', $commandTester->getDisplay());
    }
}
