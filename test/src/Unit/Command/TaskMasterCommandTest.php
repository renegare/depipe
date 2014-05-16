<?php

namespace App\Test\Unit\Command;

class TaskMasterCommandTest extends \PHPUnit_Framework_TestCase {

    public function testRegisteringTasks() {
        $mockTaskMaster = $this->getMockForAbstractClass('App\Command\TaskMasterCommand', [], '', false);

        $mockTask = $this->getMockForAbstractClass('App\Task', [], '', false, true, true, ['setLogger']);
        $mockTask->expects($this->once())
            ->method('setLogger')
            ->will($this->returnCallback(function($logger) use ($mockTaskMaster) {
                $this->assertSame($mockTaskMaster, $logger);
            }));

        $mockTaskMaster->registerTask('test-task', $mockTask);
        $this->assertSame($mockTask, $mockTaskMaster->getTask('test-task'));
    }
}
