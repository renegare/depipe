<?php

if(!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', realpath(__DIR__ . '/../'));
}

require PROJECT_ROOT . '/vendor/antecedent/patchwork/Patchwork.php';
$loader = require PROJECT_ROOT.'/vendor/autoload.php';
$loader->setPsr4('App\Test\\', __DIR__ . '/src');
