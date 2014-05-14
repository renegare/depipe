<?php

namespace App\Test\Command;

use App\Test\Util\ConsoleTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class BuildImageCommandTest extends ConsoleTestCase {

    public function testExecution() {
        $command = $this->getCommand('pipe:build');
        $this->assertInstanceOf('App\Command\BuildImageCommand', $command);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertContains('Build Complete', $commandTester->getDisplay());
    }
}
