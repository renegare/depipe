<?php

namespace App\Platform\Aws;

use App\Platform\ClientInterface;
use App\Platform\InstanceInterface;
use App\Platform\ImageInterface;
use App\Platform\LoadBalancerInterface;
use App\Platform\InstanceAccessInterface;
use Aws\Ec2\Ec2Client;
use Symfony\Component\Yaml\Dumper;
use App\Util\SSHClient;
use Psr\Log\LoggerTrait;
use Psr\Log\LoggerAwareTrait;

class Client implements ClientInterface {
    use LoggerTrait, LoggerAwareTrait;

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
        $this->debug(sprintf('Requesting describeInstances of: %s', implode(', ', $instances)));

        $response = $this->getEc2Client()
            ->describeInstances([
                'InstanceIds' => $instances
            ]);

        $responseData = $response->toArray();

        $this->debug('Got response of describeInstances', [
            'response' => $responseData
        ]);

        $instanceObjects = [];
    $instanceDescriptions = $response->getPath('Reservations/*/Instances');
        foreach($instanceDescriptions as $k => $instanceDescription) {
            $instanceObjects[] = new Instance($instanceDescription['InstanceId'], $instanceDescription);
        }

        return $instanceObjects;
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

    public function connectInstancesToLoadBalancer(array $instances, LoadBalancerInterface $loadBalancer) {
        throw new \Exception('Not Implemented');
    }

    public function getEc2Client() {
        return Ec2Client::factory($this->getCredentials());
    }

    public function setSSHClient(SSHClient $sshClient) {
        $this->sshClient = $sshClient;
    }

    public function getSSHClient() {
        return $this->sshClient;
    }

    public function log($level, $message, array $context = array()) {
        if($this->logger) {
            $this->logger->log($level, $message, $context);
        }
    }
}
