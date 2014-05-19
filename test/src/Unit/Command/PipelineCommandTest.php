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
                'find:image_config' => 'find:image_value',
                'launch_config' => 'will_be_overriden'
            ],
            'launch vanilla instance' => [
                'launch_config' => 'launch_value'
            ],
            'build webapp' => [
                'build_config' => 'build_value'
            ],
            'connect to elb' => [
                'connect_config' => 'connect_value'
            ]
        ];

        $app->setPipeline($pipelineConfig);

        list($executionOrder, $expectedFinalConfig) = $this->mockPipeCommands($pipelineConfig, $app, [
            'user_config' => 'yeah from the begining'
        ]);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);
        $this->assertContains('Pipeline completed', $commandTester->getDisplay());

        $finalConfig = $app->getConfig();
        $this->assertEquals($expectedFinalConfig, $finalConfig);
        $this->assertEquals('launch_value', $expectedFinalConfig['launch_config']);
        $this->assertEquals([
            'find:image',
            'launch',
            'build',
            'connect'
        ], $executionOrder->order);
    }

    /**
     * Assert pipe special config @from will get use config from gran-pipe (not immediate parent)
     */
    public function testSpecialFromParam() {
        $app = $this->getApplication();
        $command = $app->find('pipeline');
        $this->assertInstanceOf('App\Command\PipelineCommand', $command);

        $pipelineConfig = [
            'launch web app instance' => [
                'launch_config' => 'launch_value',
                'instance_count' => 2
            ],
            'launch db instance' => [
                'launch_config' => 'launch_value',
                'instance_count' => 1
            ],
            'connect web apps to elb' => [
                '@from' => 'launch web app instance',
                'connect_config' => 'connect_value'
            ]
        ];
        $app->setPipeline($pipelineConfig);

        list($executionOrder, $expectedFinalConfig) = $this->mockPipeCommands($pipelineConfig, $app, [
            'user_config' => 'yeah from the begining'
        ]);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);
        $this->assertContains('Pipeline completed', $commandTester->getDisplay());

        $finalConfig = $app->getConfig();
        $this->assertArrayNotHasKey('@from', $finalConfig);
        $this->assertArrayHasKey('instance_count', $finalConfig);
        $this->assertEquals(2, $finalConfig['instance_count']);
        $expectedFinalConfig['instance_count'] = 2;
        $this->assertEquals($expectedFinalConfig, $finalConfig);
        $this->assertEquals([
            'launch',
            'launch',
            'connect'
        ], $executionOrder->order);
    }

    public function mockPipeCommands(array $pipelineConfig, Application $app, array $expectedFinalConfig = []) {

        $app->setConfig($expectedFinalConfig);
        $executionOrder = new \stdClass;
        $executionOrder->order = [];
        $mockedCommands = [];
        foreach($pipelineConfig as $command => $config) {
            preg_match('/^([\w:]+)(.*)/', $command, $match);
            list($match, $commandName, $description) = $match;
            $expectedFinalConfig = array_merge($expectedFinalConfig, $this->stripOutSpecialParams($config));

            $artifactKey = $commandName . '_artifact';
            $artifactValue = $commandName . '_artifact_value';
            $expectedFinalConfig[$artifactKey] = $artifactValue;

            if(!isset($mockedCommands[$commandName])) {
                $mockedCommands[$commandName] = [$this->mockCommand($app, $commandName), []];
            }

            $mockCommand = $mockedCommands[$commandName][0];
            $mockedCommands[$commandName][1][] = function() use ($mockCommand, $artifactKey, $artifactValue, $commandName, &$executionOrder){
                    $mockCommand->set($artifactKey, $artifactValue);
                    $executionOrder->order[] = $mockCommand->getName();
            };
        }

        // lets aggregate all the calls to doExecute for each command
        foreach($mockedCommands as $mock) {
            list($mockCommand, $doExecuteCallbacks) = $mock;

            $mockCommand->expects($this->exactly(count($doExecuteCallbacks)))
                ->method('doExecute')
                ->will($this->returnCallback(function() use ($mockCommand, &$mockedCommands){
                    $callback = array_shift($mockedCommands[$mockCommand->getName()][1]);
                    $callback();
                }));
            $app->add($mockCommand);
        }

        return [$executionOrder, $expectedFinalConfig];
    }

    // public function mockCommandAggregated()

    public function stripOutSpecialParams($config) {
        foreach($config as $key => $value) {
            if(preg_match('/^@/', $key)) {
                unset($config[$key]);
            }
        }
        return $config;
    }
}
