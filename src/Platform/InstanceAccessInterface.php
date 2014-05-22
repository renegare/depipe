<?php

namespace App\Platform;

interface InstanceAccessInterface {

    /**
     * connect to a given host
     * @param string $host - host/IP address of the instance/target
     * @throws Exception - if connection fails
     */
    public function connect($host);

    /**
     * execute code on the instance (e.g. shell script for linux)
     * @param string $code - code to execute
     * @throws Exception - if execution fails
     */
    public function exec($code);
}
