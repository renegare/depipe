<?php

namespace App\Platform\Aws;

use App\Platform\InstanceInterface;
use App\Platform\InstanceAccessInterface;

class Instance extends Object implements InstanceInterface{

    /**
     * get the public hostname or IP of the instance
     * @return string - hostname or IP
     */
    public function getHost() {
        return $this->description['PublicDnsName'];
    }

    /**
     * {@inheritdoc}
     */
    public function provisionWith(InstanceAccessInterface $access, array $scripts) {
        $access->connect($this->getHost());
        foreach($scripts as $script) {
            $access->exec($script);
        }
    }
}
