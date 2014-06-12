<?php

namespace App\Platform\Aws;

use Symfony\Component\Yaml\Dumper;
use Psr\Log\LoggerTrait;
use Psr\Log\LoggerAwareTrait;

use Aws\Ec2\Ec2Client;
use Aws\ElasticLoadBalancing\ElasticLoadBalancingClient;
use Aws\Ec2\Enum\ImageState;

use App\Platform\ClientInterface;
use App\Platform\InstanceInterface;
use App\Platform\ImageInterface;
use App\Platform\LoadBalancerInterface;
use App\Platform\InstanceAccessInterface;

use App\Util\SSHClient;

class Client implements ClientInterface {
    use LoggerTrait, LoggerAwareTrait;

    protected $credentials;
    protected $sleepInterval = 5;

    public function setCredentials(array $credentials) {
        $this->credentials = $credentials;
        if(isset($credentials['sleep.interval'])){
            $this->sleepInterval = $credentials['sleep.interval'];
        }
    }

    public function getCredentials() {
        return $this->credentials;
    }

    public function convertToImage($imageId) {
        $this->debug(sprintf('Requesting describeImages of: %s', $imageId));
        $client = $this->getEc2Client();
        $imageNotReady = true;
        while($imageNotReady) {
            $response = $client->describeImages(['ImageIds' => [$imageId]]);
            $imageState = $response->getPath('Images/*/State')[0];
            $imageNotReady = ImageState::AVAILABLE !== $imageState;
            if($imageNotReady) {
                $this->info(sprintf('Image state is not yet available. Sleeping for %s seconds before retrying ...', $this->sleepInterval), [
                    'state' => $imageState
                ]);
                sleep($this->sleepInterval);
            }
        }

        $this->debug('Got response of describeImages', ['response' => $response->toArray()]);

        return new Image($imageId, $response->getPath('Images/0'));
    }

    public function convertToInstances(array $instances) {
        $this->info(sprintf('Requesting describeInstances of: %s ...', implode(', ', $instances)));

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
        $client = $this->getELBClient();
        $this->info(sprintf('Requesting describeLoadBalancers of: %s ...', $loadBalancer));
        $response = $client->describeLoadBalancers(['LoadBalancerNames' => [$loadBalancer]]);
        $this->debug('Got response of describeLoadBalancers', ['response' => $response->toArray()]);
        $description = $response->getPath('LoadBalancerDescriptions/0');
        return new LoadBalancer($description['LoadBalancerName'], $description);
    }

    public function snapshotInstance(InstanceInterface $instance, $imageName='') {
        $client = $this->getEc2Client();
        $this->info(sprintf('Creating a snapshot (%s) from instance %s', $imageName, $instance));
        $imageId = $client->createImage([
            'InstanceId' => (string) $instance,
            'Name' => $imageName])->getPath('ImageId');
        return $this->convertToImage($imageId);
    }

    /**
     * {@inheritdoc}
     */
    public function launchInstances(ImageInterface $image, $instanceCount = 1, array $instanceConfig=[], InstanceAccessInterface $instanceAccess = null, array $userDataConfig=[]) {
        $ec2Client = $this->getEc2Client();
        $config = array_merge($instanceConfig, [
            'ImageId' => $image->getId(),
            'MinCount' => $instanceCount,
            'MaxCount' => $instanceCount
        ]);

        if(count($userDataConfig)) {
            $yamlDumper = new Dumper();
            $config['UserData'] = base64_encode("#cloud-config\n" . $yamlDumper->dump($userDataConfig));
        }

        if($instanceAccess instanceOf InstanceAccess) {

            $keyName = $instanceAccess->getKeyName();
            $this->info(sprintf('Using provided aws instance access (as KeyName = \'%s\' for launching instance(s) ...', $keyName));
            if(!$instanceAccess->hasKey()) {
                $this->info('Provided aws instance access, does not have a key!');
                $response = $ec2Client->deleteKeyPair(array(
                    'KeyName' => $keyName
                ));
                $this->info(sprintf('Deleted \'%s\' key pair in aws', $keyName), ['response' => $response->toArray()]);

                $response = $ec2Client->createKeyPair(array(
                    'KeyName' => $keyName
                ));
                $this->info(sprintf('Created new \'%s\' key pair in aws', $keyName), ['response' => $response->toArray()]);
                $instanceAccess->setPrivateKey($response->getPath('KeyMaterial'));
            }
            $config['KeyName'] = $keyName;
        }

        $this->info(sprintf('Launching %s instance(s) ...', $instanceCount), ['request' => $config]);
        $responses = $ec2Client->runInstances($config);

        $instanceIds = $responses->getPath('Instances/*/InstanceId');
        $this->info('Waiting for instance(s) to be ready ...', ['instance.ids' => $instanceIds]);
        $ec2Client->waitUntilInstanceRunning(array('InstanceIds' => $instanceIds));

        return $this->convertToInstances($instanceIds);
    }

    public function connectInstancesToLoadBalancer(array $instances, LoadBalancerInterface $loadBalancer){
        $client = $this->getELBClient();
        $instanceCount = count($instances);
        $instanceIds = array_map(function($instance){
            return ['InstanceId' => (string) $instance];
        }, $instances);
        $config  = [
            'LoadBalancerName' => (string) $loadBalancer,
            'Instances' => $instanceIds
        ];

        $this->info(sprintf('Requesting registerInstancesWithLoadBalancer for: %s (%s) ...', $loadBalancer, implode(',', $instances)), $config);

        $response = $client->registerInstancesWithLoadBalancer($config);
        $this->debug('Got response of registerInstancesWithLoadBalancer', ['response' => $response->toArray()]);

        $this->info('Waiting for instances to be healthy ...');

        $healthyCount = 0;
        while($healthyCount < $instanceCount) {
            $response = $client->describeInstanceHealth($config);
            $healthyCount = 0;
            foreach($response->getPath('InstanceStates') as $instance) {
                if($instance['State'] === 'InService') {
                    ++$healthyCount;
                }
            }

            if($healthyCount < $instanceCount) {
                $this->info(sprintf('%s/%s instances healthy. Will recheck in %s seconds ...', $healthyCount, $instanceCount, $this->sleepInterval));
                sleep($this->sleepInterval);
            }
        }

        $this->info('Instances are all healthy!');
    }

    public function getEc2Client() {
        return Ec2Client::factory($this->getCredentials());
    }

    public function getELBClient() {
        return ElasticLoadBalancingClient::factory($this->getCredentials());
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

    public function findImage($imageName) {
        $this->debug(sprintf('Requesting describeImages of: %s', $imageName));
        $client = $this->getEc2Client();
        $response = $client->describeImages([
            'Filters' => [
                ['Name' => 'name', 'Values' => [$imageName]]]]);

        $this->debug('Got response of describeImages', ['response' => $response->toArray()]);
        return new Image($response->getPath('Images/0/ImageId'), $response->getPath('Images/0'));
    }
}
