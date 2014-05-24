<?php

namespace App\Test\Util;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Dumper;

abstract class ConsoleTestCase extends BaseTestCase {

    protected function getApplication() {
        ob_start();
        $return = true;
        $app = require PROJECT_ROOT . '/app';
        ob_clean();
        return $app;
    }

    protected function getCommand($name) {
        return $this->getApplication()
            ->find($name);
    }

    protected function mockCommand(Application $app, $name, array $methods = []) {
        $mockCommand = $this->getMockForAbstractClass('App\Command', [$name], '', true, true, true, $methods);
        $app->add($mockCommand);
        $command = $app->find($name);
        $this->assertSame($command, $mockCommand);
        return $mockCommand;
    }

    protected function mockTask($taskName, $taskMasterCommand, array $expectedSetters = [], $mockResponse = null) {
        $taskMasterCommand->registerTask($taskName, $this->getMockTask($expectedSetters, $mockResponse));
    }

    protected function getMockTask(array $expectedSetters = [], $mockResponse = null) {
        $mockTask = $this->getMockBuilder('App\Task')
            ->setMethods(['__call', 'doRun', 'run'])
            ->disableOriginalConstructor()
            ->getMock()
            ;

        $mockTask->expects($this->exactly(count($expectedSetters) + 1))
            ->method('__call')
            ->will($this->returnCallback(function($name, $arguments) use ($expectedSetters, $mockTask) {
                $this->assertCount(1, $arguments);
                $value = $arguments[0];

                if($name === 'setLogger') {
                    $this->assertInstanceOf('Psr\Log\LoggerInterface', $value);
                    return $mockTask;
                }

                $name = $this->deCamelCase(preg_replace('/^set/', '', $name));

                if(isset($expectedSetters[$name])) {
                    $expectation = $expectedSetters[$name];
                    if($expectation instanceof \Closure) {
                        $expectation($value);
                    } else {
                        $this->assertEquals($expectation, $value);
                    }
                    return $mockTask;
                }

                throw new \Exception(sprintf('Unexpected option set: %s', $name));
            }));

        $mockTask->expects($this->once())
            ->method('run')
            ->will($this->returnValue($mockResponse));

        return $mockTask;
    }

    protected function deCamelCase($name) {
        $name = preg_replace('/([A-Z])/', ' $1', $name);
        return strtolower(str_replace(' ', '_', trim($name)));
    }

    protected function getMockCommand($name = 'test') {
        return $this->getMockForAbstractClass('App\Command', [$name]);
    }
}
