<?php

namespace App\Test\Util;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Dumper;

abstract class ConsoleTestCase extends \PHPUnit_Framework_TestCase
{

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

    protected function getMockedCommand($name, $className, array $methods = null) {

        $builder = $this->getMockBuilder($className);

        if($methods && count($methods) > 0) {
            $builder->setMethods($methods);
        }

        $mockCommand = $builder->getMock();
        $app = $this->getApplication();
        $app->add($mockCommand);

        $command = $app->find($name);

        $this->assertSame($command, $mockCommand);

        return $mockCommand;
    }
}
