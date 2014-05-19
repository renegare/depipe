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

        $mockInstance = $this->getMock('App\Platform\InstanceInterface');
        $mockImage = $this->getMock('App\Platform\ImageInterface');
        $mockClient = $this->getMock('App\Platform\ClientInterface');
        $mockClient->expects($this->once())
            ->method('launchInstances')
            ->will($this->returnCallback(function($image, $instanceCount, $instanceConfig, $userDataConfig) use ($expectedConfig, $mockInstance, $mockImage){
                $this->assertEquals($image, $mockImage);
                $this->assertEquals($instanceCount, $expectedConfig['instance_count']);
                $this->assertEquals($userDataConfig, $expectedConfig['userdata_config']);
                $this->assertEquals($instanceConfig, $expectedConfig['instance_config']);
                return [$mockInstance];
            }));
        $mockClient->expects($this->once())
            ->method('provisionInstances')
            ->will($this->returnCallback(function($instances, $shellScripts) use ($expectedConfig, $mockInstance){
                $this->assertEquals([$mockInstance], $instances);
                $this->assertEquals($shellScripts, $expectedConfig['shell_scripts']);
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
