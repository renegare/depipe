<?php

namespace App\Test\Unit;

use App\Command\GenerateBreakDownCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Output\OutputInterface;
use Psr\Log\LogLevel;

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

        $command = $this->getMockForAbstractClass('App\Command', [], '', false);
        $command->setLogger($mockLogger);
        $command->info('mock-message', ['mock' => 'context']);
    }



    public function testVerbosityLogger() {
        $expectedAllowedLogLevel = [
            LogLevel::EMERGENCY,
            LogLevel::ALERT,
            LogLevel::CRITICAL,
            LogLevel::ERROR,
            LogLevel::WARNING,
            LogLevel::NOTICE,
            LogLevel::INFO,
            LogLevel::DEBUG
        ];
        $verbosityLevel = OutputInterface::VERBOSITY_DEBUG;

        $mockOutput = $this->getMockForAbstractClass('Symfony\Component\Console\Output\OutputInterface');
        $mockOutput->expects($this->exactly(count($expectedAllowedLogLevel)))
            ->method('writeln')
            ->will($this->returnCallback(function($message) use ($expectedAllowedLogLevel){
                preg_match('/^\[(\w+)\]/', $message, $match);
                $this->assertInternalType('array', $match);
                $level = $match[1];
                $this->assertContains($level, $expectedAllowedLogLevel);
            }));

        $mockOutput->expects($this->any())
            ->method('getVerbosity')
            ->will($this->returnValue($verbosityLevel));

        $command = $this->getMockForAbstractClass('App\Command', [], '', false);
        $command->setOutput($mockOutput);

        foreach($expectedAllowedLogLevel as $level) {
            $command->$level('mock-message');
        }
    }

    /**
     * @expectedException App\Test\Unit\AbstractCommandTestException
     */
    public function testExecute() {
        $command = $this->getMockForAbstractClass('App\Command', ['doExecute', 'configure'], '', true);
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
