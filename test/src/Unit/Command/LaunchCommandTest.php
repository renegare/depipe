<?php

namespace App\Test\Command;

use App\Test\Util\ConsoleTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class LaunchCommandTest extends ConsoleTestCase {

    /**
     * Assert that the command launches an instance running the following tasks:
     * - Given I have an image (base image)
     * - AND I have a client
     * - AND I have userdata_config
     * - AND I have provisioning shell_scripts
     * - AND I have instance_config
     * - AND I have instance_count
     *
     * - RUN get_client task: [credentials] : client
     * - RUN launch_instances task: [client, userdata_config, image, instance_config, instance_count] : [instance(s)]
     * - RUN provision_instances task: [client, [instance(s)], shell_scripts] : void
     */
    public function testExecution() {
        $app = $this->getApplication();
        $command = $app->find('launch');
        $this->assertInstanceOf('App\Command\LaunchCommand', $command);
        $mockClient = $this->getMock('App\Client');

        $expectedConfig = [
            'client' => $mockClient,
            'image' => 'image-1234abc5',
            'userdata_config' => [
                'runcmd' => [
                    "echo $(date) > running.since"]],
            'shell_scripts' => ['dumm_script.sh'],
            'instance_config' =>[
                'region' => 'north-mars-1',
                'size' => 'insignificant'
            ],
            'instance_count' => 1
        ];

        $app->setConfig($expectedConfig);

        $mockInstance = $this->getMock('App\Instance');
        $this->mockTask('launch_instances', $command, [
            'client' => $mockClient,
            'image' => $expectedConfig['image'],
            'userdata_config' => $expectedConfig['userdata_config'],
            'instance_config' => $expectedConfig['instance_config']], [$mockInstance]);

        $this->mockTask('provision_instances', $command, [
            'client' => $mockClient,
            'instance' => [$mockInstance],
            'shell_scripts' => $expectedConfig['shell_scripts']]);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);
        $this->assertContains('Launched 1 instance(s)', $commandTester->getDisplay());
    }
}