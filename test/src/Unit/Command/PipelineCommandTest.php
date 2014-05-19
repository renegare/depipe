<?php

namespace App\Test\Command;

use App\Test\Util\ConsoleTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Application;

class PipelineCommandTest extends ConsoleTestCase {

    /**
     * Assert that the command executes a pipe line definition
     * - Given I have pipeline configurations
     * - Then it should run through each command
     */
    public function testExecution() {
        $app = $this->getApplication();
        $command = $app->find('pipeline');
        $this->assertInstanceOf('App\Command\PipelineCommand', $command);

        $pipelineConfig = [
            'find:image web app' => [
                'launch_config' => 'launch_value'
            ],
            'launch vanilla instance' => [
                'launch_config' => 'launch_value'
            ],
            'build webapp' => [
                'build_config' => 'build_value'
            ],
            'connect to elb' => [
                'connect_config' => 'connect_value'
            ],
        ];
        $app->setPipeline($pipelineConfig);

        list($executionOrder, $expectedFinalConfig) = $this->mockPipeCommands($pipelineConfig, $app, [
            'user_config' => 'yeah from the begining'
        ]);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);
        $this->assertContains('Pipeline completed', $commandTester->getDisplay());

        $this->assertEquals($expectedFinalConfig, $app->getConfig());
        $this->assertEquals([
            'find:image',
            'launch',
            'build',
            'connect'
        ], $executionOrder->order);
    }

    public function mockPipeCommands(array $pipelineConfig, Application $app, array $expectedFinalConfig = []) {

        $app->setConfig($expectedFinalConfig);
        $executionOrder = new \stdClass;
        $executionOrder->order = [];
        foreach($pipelineConfig as $command => $config) {
            preg_match('/^([\w:]+)(.*)/', $command, $match);
            list($match, $commandName, $description) = $match;
            $expectedFinalConfig = array_merge($expectedFinalConfig, $config);

            $artifactKey = $commandName . '_artifact';
            $artifactValue = $commandName . '_artifact_value';
            $expectedFinalConfig[$artifactKey] = $artifactValue;

            $mockCommand = $this->mockCommand($app, $commandName);
            $mockCommand->expects($this->once())
                ->method('doExecute')
                ->will($this->returnCallback(function() use ($mockCommand, $artifactKey, $artifactValue, $commandName, &$executionOrder){
                    $mockCommand->set($artifactKey, $artifactValue);
                    $executionOrder->order[] = $mockCommand->getName();
                }));
            $app->add($mockCommand);
        }
        return [$executionOrder, $expectedFinalConfig];
    }
}
