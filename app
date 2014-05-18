#!/usr/bin/env php
<?php

if(!isset($return)) {
    function handleError($errno, $errstr, $errfile, $errline, array $errcontext)
    {
        if (0 === error_reporting()) {
            return false;
        }

        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }
    set_error_handler('handleError');
}

require_once __DIR__ . '/vendor/autoload.php';


# create application
$application = new App\Console('DePipe', '@package_version@');

// register commands
$application->add(new App\Command\LaunchCommand());
$application->add(new App\Command\BuildCommand());
$application->add(new App\Command\ConnectCommand());

// run application
if(isset($return) && $return === true) {
    return $application;
} else {
    $application->run();
}
