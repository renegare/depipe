<?php

namespace App\Test\Command;

use App\Test\Util\ConsoleTestCase;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Yaml\Dumper;

class ConsoleTest extends ConsoleTestCase {

    /**
     * assert --log|-l option
     */
    public function testLogOption() {
        $expectedLog = PROJECT_ROOT . '/depipe.log';
        @unlink($expectedLog);
        $app = $this->getApplication();
        $this->assertInstanceOf('App\Console', $app);

        $app->setAutoExit(false);

        $command = $this->getMockForAbstractClass('App\Command', ['doExecute', 'configure'], '', true);
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
            '-l' => $expectedLog
        ]);

        $this->assertTrue(file_exists($expectedLog));
        $this->assertContains('test log!', file_get_contents($expectedLog));
        @unlink($expectedLog);
    }

    /**
     * assert --config|-c option
     */
    public function testConfigOption() {
        @unlink('depipe.yml');
        $dumper = new Dumper();
        file_put_contents('depipe.yml', $dumper->dump(['base-ami' => 'ami-test']));

        $app = $this->getApplication();
        $app->setAutoExit(false);

        $appTester = new ApplicationTester($app);
        $appTester->run([
            '--config' => 'depipe.yml'
        ]);

        $this->assertEquals(['base-ami' => 'ami-test'], $app->getConfig());
        @unlink('depipe.yml');
    }
}
