<?php

namespace App\Command;

use App\AbstractCommand;
use App\AbstractTask;

abstract class TaskMasterCommand extends AbstractCommand
{
    public function registerTask($name, AbstractTask $task) {
        $task->setLogger($this);
        $this->tasks[$name] = $task;
    }

    public function getTask($name) {
        return $this->tasks[$name];
    }
}
