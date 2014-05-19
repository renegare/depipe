<?php

namespace App\Test\Command;

use App\Test\Util\ConsoleTestCase;
use Symfony\Component\Console\Tester\CommandTester;

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

        $mockCommand = $this->mockCommand('launch');
        $mockCommand->expects($this->once())
            ->method('doExecute')
            ->will($this->returnCallback(function() use ($mockCommand){
                $mockCommand->set('new_config', 'new_value');
            }));
        $app->add($mockCommand);

        $pipeLineConfig = [
            'typical pipe line' => [
                'launch vanilla instance' => ['...']
            ]
        ];

        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);
        $this->assertContains('Pipeline completed', $commandTester->getDisplay());
    }
}
