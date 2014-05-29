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
use Aws\Ec2\Enum\ImageState;

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
        $this->info(sprintf('Requesting describeImages of: %s', $imageId));
        $client = $this->getEc2Client();
        $imageState = null;
        while(ImageState::AVAILABLE !== $imageState[0]) {
            $response = $client->describeImages(['ImageIds' => [$imageId]]);
            $imageState = $response->getPath('Images/*/State');
        }

        $this->debug('Got response of describeImages', ['response' => $response->toArray()]);

        return new Image($imageId, $response->getPath('Images/0'));
    }

    public function convertToInstances(array $instances) {
        $this->info(sprintf('Requesting describeInstances of: %s', implode(', ', $instances)));

        $response = $this->getEc2Client()
            ->describeInstances([
                'InstanceIds' => $instances
            ]);

        $this->debug('Got response of describeInstances', ['response' => $response->toArray()]);

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
        $client = $this->getEc2Client();
        $this->info(sprintf('Creating a snapshot (%s) from instance %s', $imageName, $instance));
        $imageId = $client->createImage([
            'InstanceId' => (string) $instance,
            'Name' => $imageName])->getPath('ImageId');
        return $this->convertToImage($imageId);
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

        $this->info(sprintf('Launching %s instance(s) ...', $instanceCount), ['request' => $config]);
        $ec2Client = $this->getEc2Client();
        $responses = $ec2Client->runInstances($config);

        $instanceIds = $responses->getPath('Instances/*/InstanceId');
        $this->info('Waiting for instance(s) to be ready ...', ['instance.ids' => $instanceIds]);
        $ec2Client->waitUntilInstanceRunning(array('InstanceIds' => $instanceIds));

        return $this->convertToInstances($instanceIds);
    }

    public function connectInstancesToLoadBalancer(array $instances, LoadBalancerInterface $loadBalancer) {
        throw new \Exception('Not Implemented');
    }

    public function getEc2Client() {
        return Ec2Client::factory($this->getCredentials());
    }

    public function log($level, $message, array $context = array()) {
        if($this->logger) {
            $this->logger->log($level, $message, $context);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function killInstances(array $instances) {
        $this->debug(sprintf('Requesting terminateInstances of: %s', implode(', ', $instances)));
        $response = $this->getEc2Client()
            ->terminateInstances([
                'InstanceIds' => array_map(function($instance){
                    return (string) $instance;
                }, $instances)
            ]);
        $this->debug('Got response of terminateInstances', ['response' => $response->toArray()]);
    }
}
