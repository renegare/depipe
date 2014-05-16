<?php

namespace App\Command;

use App\Task;
use App\Command;

abstract class TaskMasterCommand extends Command
{
    public function registerTask($name, Task $task) {
        $task->setLogger($this);
        $this->tasks[$name] = $task;
    }

    public function getTask($name) {
        return $this->tasks[$name];
    }
}
