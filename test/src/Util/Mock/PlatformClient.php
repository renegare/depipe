<?php

namespace App\Test\Util\Mock;

use App\Platform\ClientInterface;
use App\Platform\InstanceInterface;
use App\Platform\ImageInterface;
use App\Platform\LoadBalancerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LoggerAwareTrait;

class PlatformClient implements ClientInterface {
    use LoggerTrait, LoggerAwareTrait;

    protected $credentials;

    public function setCredentials(array $credentials) {
        $this->credentials = $credentials;
    }

    public function getCredentials() {
        return $this->credentials;
    }

    public function convertToImage($imageId) {
        throw new \Exception('Not Implemented');
    }

    public function convertToInstances(array $instances) {
        throw new \Exception('Not Implemented');
    }

    public function convertToLoadBalancer($loadBalancer) {
        throw new \Exception('Not Implemented');
    }

    public function snapshotInstance(InstanceInterface $instance, $imageName='') {
        throw new \Exception('Not Implemented');
    }

    public function launchInstances(ImageInterface $image, $instanceCount = 1, array $instanceConfig=[], array $userDataConfig=[]) {
        throw new \Exception('Not Implemented');
    }

    public function provisionInstances(array $instances, array $shellScripts, $user = 'root', $privateKey = null) {
        throw new \Exception('Not Implemented');
    }

    public function connectInstancesToLoadBalancer(array $instances, LoadBalancerInterface $loadBalancer) {
        throw new \Exception('Not Implemented');
    }

    public function log($level, $message, array $context = array()) {
        if($this->logger) {
            $this->logger->log($level, $message, $context);
        }
    }
}
