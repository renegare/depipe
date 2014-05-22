<?php

namespace App\Platform;

use App\Platform\InstanceInterface;
use App\Platform\ImageInterface;
use App\Platform\LoadBalancerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareInterface;

interface ClientInterface extends LoggerAwareInterface, LoggerInterface {

    /**
     * set client credentials
     * @param array $credentials
     * @return void
     */
    public function setCredentials(array $credentials);

    /**
     * get client credentials
     * @return array $credentials
     */
    public function getCredentials();

    /**
     * converts a vendor specific int|string and converts that into an image object.
     * If the image does not exist in the platform, then an exception should be thrown.
     * @param int|string $imageId
     * @throws Exception
     * @return ImageInterface
     */
    public function convertToImage($imageId);

    /**
     * converts an array of vendor specific int|string and converts that into and
     * array of  instance objects.
     * If the instances do not exist in the platform, then an exception should be thrown.
     * @param array $imageId
     * @throws Exception
     * @return array
     */
    public function convertToInstances(array $instances);

    /**
     * converts a vendor specific int|string and converts that into a loadBalancer object.
     * If the loadBalancer does not exist in the platform, then an exception should be thrown.
     * @param int|string $imageId
     * @throws Exception
     * @return LoadBalancerInterface
     */
    public function convertToLoadBalancer($loadBalancer);

    /**
     * takes an instance and creates a snapshot of the running instance
     * @param InstanceInterface $instance
     * @param string $imageName - name of the resulting image
     * @throws Exception - from the platform
     * @return ImageInterface
     */
    public function snapshotInstance(InstanceInterface $instance, $imageName='');

    /**
     * launches instances from a given image
     * @param ImageInterface $imageName - image to launch instances from
     * @param int $instanceCount - number of instances to launch
     * @param array $insanceConfig - platform specific configuration
     * @param array $userDataConfig - cloudinit-esque config, use only if you image/platform supports it
     * @throws Exception - from the platform
     * @return array - of InstanceInterface(s)
     */
    public function launchInstances(ImageInterface $image, $instanceCount = 1, array $instanceConfig=[], array $userDataConfig=[]);

    /**
     * attempts to login into the instances execute the shellscripts
     * Note: this method is rather limited to linux machines and not so elegant
     * handling of ssh private keys to get access ... so things may change in due
     * time.
     * @param array $instances - of InstanceInterface(s)
     * @param array $shellScripts - of shell script file paths
     * @param string $user - user to connect to the instances with
     * @param string $privateKey - to use to authenticate with
     * @throws Exception - from the platform / ssh client|connection
     * @return void
     */
    public function provisionInstances(array $instances, array $shellScripts, $user = 'root', $privateKey = null);

    /**
     * Connects instances to a load balancer
     * @param array - of InstanceInterface(s)
     * @param LoadBalancer $loadbalancer
     * @throws Exception - from the platform
     * @return void
     */
    public function connectInstancesToLoadBalancer(array $instances, LoadBalancerInterface $loadBalancer);
}
