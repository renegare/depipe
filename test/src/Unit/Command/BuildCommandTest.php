<?php

namespace App\Test\Command;

use App\Test\Util\ConsoleTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class BuildCommandTest extends ConsoleTestCase {

    /**
     * Assert that the command builds an image running the following tasks:
     * - Given I have [instance(s)]
     * - AND I have a client
     * - AND I have a image_name
     *
     * - RUN snapshot_instance task: [client, instances[0], image_name] : image
     *
     * Note: expects instances to be an array of instance objects. Will only
     * use the first instance. This seems odd ... but humour me ...
     */
    public function testExecution() {
        $app = $this->getApplication();
        $command = $app->find('build');
        $this->assertInstanceOf('App\Command\BuildCommand', $command);

        $mockInstances = [$this->getMock('App\Platform\InstanceInterface'),    $this->getMock('App\Platform\InstanceInterface')];
        $mockClient = $this->getMock('App\Platform\ClientInterface');
        $mockImage = $this->getMock('App\Platform\ImageInterface');

        $expectedConfig = [
            'instances' => ['i-123456', 'i-098765'],
            'image_name' => 'new-image'
        ];
        $app->setConfig($expectedConfig);

        $mockClient->expects($this->once())
            ->method('snapshotInstance')
            ->will($this->returnCallback(function($instance, $imageName) use ($mockImage, $mockInstances){
                $this->assertEquals($mockInstances[0], $instance);
                return $mockImage;
            }));
        $mockClient->expects($this->once())
            ->method('convertToInstances')
            ->will($this->returnCallback(function($instances) use ($expectedConfig, $mockInstances){
                $this->assertEquals($expectedConfig['instances'], $instances);
                return $mockInstances;
            }));
        $mockClient->expects($this->once())
            ->method('convertToImage')
            ->will($this->returnCallback(function($image) use ($mockImage){
                $this->assertEquals($mockImage, $image);
                return $mockImage;
            }));
        $app->setClient($mockClient);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $builtImage = $app->getConfigValue('image');
        $this->assertEquals($builtImage, $mockImage);
        $this->assertContains('Built image new-image', $commandTester->getDisplay());
    }

    /**
     * yaml configuration cannot contain intances (it is potentially an unknown and complicated object!)
     * so realistically, build command will need to rely on a fallback.
     * This fallback is to call the launch command which will set the 'instances' config
     */
    public function testInstancesPipeIn() {
        $app = $this->getApplication();
        $command = $app->find('build');
        $mockInstances = [$this->getMock('App\Platform\InstanceInterface')];
        $mockClient = $this->getMock('App\Platform\ClientInterface');
        $mockImage = $this->getMock('App\Platform\ImageInterface');

        $mockLaunchCommand = $this->getMockForAbstractClass('App\Command', ['doExecute', 'configure'], '', true);
        $mockLaunchCommand->expects($this->once())
            ->method('doExecute')
            ->will($this->returnCallback(function() use ($app, $mockInstances){
                $app->setConfigValue('instances', $mockInstances);
            }));
        $mockLaunchCommand->setName('launch');
        $app->add($mockLaunchCommand);

        $expectedConfig = [
            'image_name' => 'new-image'
        ];
        $app->setConfig($expectedConfig);

        $mockClient->expects($this->once())
            ->method('snapshotInstance')
            ->will($this->returnCallback(function($instance, $imageName) use ($mockImage, $mockInstances){
                $this->assertEquals($mockInstances[0], $instance);
                return $mockImage;
            }));
        $mockClient->expects($this->any())
            ->method('convertToInstances')
            ->will($this->returnCallback(function($instances) use ($expectedConfig, $mockInstances){
                $this->assertEquals($mockInstances, $instances);
                return $mockInstances;
            }));
        $app->setClient($mockClient);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $config = $app->getConfig();
        $this->assertEquals($config['image'], $mockImage);
        $this->assertContains('Built image new-image', $commandTester->getDisplay());

    }
}
