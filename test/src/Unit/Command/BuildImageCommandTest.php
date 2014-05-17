<?php

namespace App\Test\Command;

use App\Test\Util\ConsoleTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class BuildImageCommandTest extends ConsoleTestCase {

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
        $command = $app->find('pipe:build');
        $this->assertInstanceOf('App\Command\BuildImageCommand', $command);

        $mockInstances = [$this->getMock('App\Instance')];
        $mockClient = [$this->getMock('App\Client')];

        $expectedConfig = [
            'instances' => $mockInstances,
            'client' => $mockClient,
            'image_name' => 'new-image'
        ];

        $app->setConfig($expectedConfig);

        $mockImage = $this->getMock('App\Image');
        $this->mockTask('snapshot_instance', $command, [
            'client' => $mockClient,
            'instance' => $mockInstances[0],
            'image_name' => $expectedConfig['image_name']], $mockImage);;

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
        $mockInstances = [$this->getMock('App\Instance')];
        $mockClient = $this->getMock('App\Client');
        $mockImage = $this->getMock('App\Image');

        $mockLaunchCommand = $this->getMockForAbstractClass('App\Command', ['doExecute', 'configure'], '', true);
        $mockLaunchCommand->expects($this->once())
            ->method('doExecute')
            ->will($this->returnCallback(function() use ($app, $mockInstances){
                $app->setConfigValue('instances', $mockInstances);
            }));
        $mockLaunchCommand->setName('launch');
        $app->add($mockLaunchCommand);

        $expectedConfig = [
            'client' => $mockClient,
            'image_name' => 'new-image'
        ];
        $app->setConfig($expectedConfig);

        $command = $app->find('pipe:build');
        $this->mockTask('snapshot_instance', $command, [
            'client' => $mockClient,
            'instance' => $mockInstances[0],
            'image_name' => $expectedConfig['image_name']], $mockImage);;

        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $builtImage = $app->getConfigValue('image');
        $this->assertEquals($builtImage, $mockImage);
        $this->assertContains('Built image new-image', $commandTester->getDisplay());

    }
}
