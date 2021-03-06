<?php

namespace App\Platform;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareInterface;

interface InstanceAccessInterface extends LoggerInterface, LoggerAwareInterface {

    /**
     * connect to a given host
     * @param array $credentials - credentials to login via the respective method
     * @return void
     */
    public function setCredentials(array $credentials);

    /**
     * connect to a given host
     * @param string $host - host/IP address of the instance/target
     * @throws Exception - if connection fails
     * @return void
     */
    public function connect($host);

    /**
     * execute code on the instance (e.g. shell script for linux)
     * @param string $code - code to execute
     * @param Closure $callback - callback to execute on every response output
     * @throws Exception - if execution fails
     * @return void
     */
    public function exec($code, \Closure $callback = null);
}
