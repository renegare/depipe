<?php

namespace App\Platform\Aws;

use App\Platform\InstanceInterface;

class Instance extends Object implements InstanceInterface{

    /**
     * get the public hostname or IP of the instance
     * @return string - hostname or IP
     */
    public function getHostName() {
        return $this->description['PublicDnsName'];
    }
}
