<?php

namespace App\Test\Command;

use App\Test\Util\ConsoleTestCase;
use Symfony\Component\Console\Tester\ApplicationTester;

class ConsoleTest extends ConsoleTestCase {

    /**
     * assert logging information is stored in a depipe.log file
     */
    public function testLogging() {
        $expectedLog = PROJECT_ROOT . '/depipe.log';
        @unlink($expectedLog);
        $app = $this->getApplication();
        $this->assertInstanceOf('App\Console', $app);

        $app->setAutoExit(false);

        $command = $this->getMockForAbstractClass('App\AbstractCommand', ['doExecute', 'configure'], '', true);
        $command->expects($this->once())
            ->method('doExecute')
            ->will($this->returnCallback(function() use ($command){
                $command->info('test log!');
            }));
        $command->setName('test');
        $app->add($command);

        $appTester = new ApplicationTester($app);
        $appTester->run([
            'command' => $command->getName(),
            '--log' => $expectedLog
        ]);

        $this->assertTrue(file_exists($expectedLog));
        $this->assertContains('test log!', file_get_contents($expectedLog));
    }
}
