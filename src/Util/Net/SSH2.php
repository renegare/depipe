<?php

namespace App\Util\Net;

class SSH2 extends \Net_SSH2 {

    public function __construct($host, $port = 22, $timeout = 10) {
        parent::Net_SSH2($host, $port, $timeout);
    }

    public function __destruct() {
        return @parent::__destruct();
    }
}
