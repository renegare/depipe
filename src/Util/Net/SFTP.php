<?php

namespace App\Util\Net;

class SFTP extends \Net_SFTP {

    public function __construct($host, $port = 22, $timeout = 10) {
        parent::Net_SFTP($host, $port, $timeout);
    }

    public function __destruct() {
        return @parent::__destruct();
    }
}
