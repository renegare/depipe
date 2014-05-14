<?php

namespace App;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;

class Console extends Application {

    protected $config = [];

    public function setConfig(array $config) {
        $this->config = $config;
    }

    public function getConfig() {
        return $this->config;
    }

    public function getConfigValue($key) {
        return $this->config[$key];
    }
}
