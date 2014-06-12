<?php

namespace App\Platform\Aws;

use App\Platform\InstanceInterface;
use App\Platform\InstanceAccessInterface;
use Guzzle\Service\Resource\Model;

class Instance extends Object implements InstanceInterface{

    /**
     * get the public hostname or IP of the instance
     * @throws Exception - if no public hostname or IP can be found
     * @return string - hostname or IP
     */
    public function getHost() {
        $host = $this->description['PublicDnsName'];
        if(!$host) {
            $data = new Model($this->description);
            $publicIps = $data->getPath('NetworkInterfaces/*/PrivateIpAddresses/*/Association/PublicIp');
            if(count($publicIps) > 0) {
                $host =  $publicIps[0];
            }
        }

        if(!$host) {
            throw new \Exception(sprintf('No public hostname or IP can be found for this instance: %s', $this->id));
        }

        return $host;
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
