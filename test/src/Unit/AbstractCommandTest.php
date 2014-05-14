<?php

namespace App\Test\Unit;

use App\Command\GenerateBreakDownCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class AbstractCommandTest extends \PHPUnit_Framework_TestCase {

    public function testLogger() {
        $mockLogger = $this->getMockForAbstractClass('Psr\Log\LoggerInterface');
        $mockLogger->expects($this->once())
            ->method('log')
            ->will($this->returnCallback(function($level, $message, $context) {
                $this->assertEquals('info', $level);
                $this->assertEquals('mock-message', $message);
                $this->assertEquals(['mock' => 'context'], $context);
            }));

        $command = $this->getMockForAbstractClass('App\AbstractCommand', [], '', false);
        $command->setLogger($mockLogger);
        $command->info('mock-message', ['mock' => 'context']);
    }

    /**
     * @expectedException App\Test\Unit\AbstractCommandTestException
     */
    public function testExecute() {
        $command = $this->getMockForAbstractClass('App\AbstractCommand', ['doExecute', 'configure'], '', true);
        $command->expects($this->once())
            ->method('doExecute')
            ->will($this->returnCallback(function(){
                throw new AbstractCommandTestException();
            }));

        $command->setName('test');

        $application = new Application();
        $application->add($command);

        $commandTester = new CommandTester($command);
        $commandTester->execute(array('command' => $command->getName()));
    }
}

class AbstractCommandTestException extends \Exception {}
