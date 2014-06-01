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

        $expectedConfig = [
            'image' => 'test-image-1234abc5',
            'userdata.config' => [
                'runcmd' => [
                    "echo $(date) > running.since"]],
            'scripts' => ['dumm_script.sh'],
            'instance.config' =>[
                'region' => 'north-mars-1',
                'size' => 'insignificant'
            ],
            'instance.access' => ['...'],
            'instance.count' => 1
        ];

        $app->setConfig($expectedConfig);
        $abstractInstanceAccess = $this->getMock('App\Platform\InstanceAccessInterface');
        $app->setInstanceAccess($abstractInstanceAccess);

        $mockInstance = $this->getMock('App\Platform\InstanceInterface');
        $mockInstance->expects($this->once())
            ->method('provisionWith')
            ->will($this->returnCallback(function($instanceAccess, $scripts) use ($expectedConfig, $mockInstance){
                $this->assertInstanceOf('App\Platform\InstanceAccessInterface', $instanceAccess);
                $this->assertEquals($scripts, $expectedConfig['scripts']);
            }));

        $mockImage = $this->getMock('App\Platform\ImageInterface');
        $mockClient = $this->getMock('App\Platform\ClientInterface');
        $mockClient->expects($this->once())
            ->method('launchInstances')
            ->will($this->returnCallback(function($image, $instanceCount, $instanceConfig, $instanceAccess, $userDataConfig) use ($expectedConfig, $mockInstance, $mockImage){
                $this->assertEquals($image, $mockImage);
                $this->assertEquals($instanceCount, $expectedConfig['instance.count']);
                $this->assertEquals($userDataConfig, $expectedConfig['userdata.config']);
                $this->assertEquals($instanceConfig, $expectedConfig['instance.config']);
                return [$mockInstance];
            }));
        $mockClient->expects($this->once())
            ->method('convertToImage')
            ->will($this->returnCallback(function($imageID) use ($expectedConfig, $mockImage){
                $this->assertEquals($expectedConfig['image'], $imageID);
                return $mockImage;
            }));
        $app->setClient($mockClient);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);
        $this->assertContains('Launched 1 instance(s)', $commandTester->getDisplay());
    }
}
