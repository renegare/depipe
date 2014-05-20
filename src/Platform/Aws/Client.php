<?php

namespace App\Platform\Aws;

use App\Platform\ClientInterface;
use App\Platform\InstanceInterface;
use App\Platform\ImageInterface;
use App\Platform\LoadBalancerInterface;
use Aws\Ec2\Ec2Client;
use Symfony\Component\Yaml\Dumper;

class Client implements ClientInterface {

    protected $credentials;

    public function setCredentials(array $credentials) {
        $this->credentials = $credentials;
    }

    public function getCredentials() {
        return $this->credentials;
    }

    public function convertToImage($imageId) {
        $response = $this->getEc2Client()
            ->describeImages([
                'ImageIds' => [$imageId]
            ]);

        return new Image($imageId, $response->getPath('Images/0'));
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
        $config = array_merge($instanceConfig, [
            'ImageId' => $image->getId(),
            'MinCount' => $instanceCount,
            'MaxCount' => $instanceCount
        ]);

        if(count($userDataConfig)) {
            $yamlDumper = new Dumper();
            $config['UserData'] = base64_encode("#cloud-config\n" . $yamlDumper->dump($userDataConfig));
        }

        $ec2Client = $this->getEc2Client();
        $responses = $ec2Client->runInstances($config);

        $instanceIds = $responses->getPath('Instances/*/InstanceId');
        $ec2Client->waitUntilInstanceRunning(array('InstanceIds' => $instanceIds));

        return $this->convertToInstances($instanceIds);
    }

    public function provisionInstances(array $instances, array $shellScripts) {
        throw new \Exception('Not Implemented');
    }

    public function connectInstancesToLoadBalancer(array $instances, LoadBalancerInterface $loadBalancer) {
        throw new \Exception('Not Implemented');
    }

    public function getEc2Client() {
        return Ec2Client::factory($this->getCredentials());
    }
}
