<?php

namespace App\Platform;

use App\Platform\InstanceInterface;
use App\Platform\ImageInterface;
use App\Platform\LoadBalancerInterface;
use App\Platform\InstanceAccessInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareInterface;;

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
     * @param array $instanceConfig - platform specific configuration
     * @param InstanceAccessInterface $instanceAccess - instanceAccess object that may or may not be used by the platform to gurantee access to the server (See platform specific implementation)
     * @param array $userDataConfig - cloudinit-esque config, use only if you image/platform supports it
     * @throws Exception - from the platform
     * @return array - of InstanceInterface(s)
     */
    public function launchInstances(ImageInterface $image, $instanceCount = 1, array $instanceConfig=[], InstanceAccessInterface $instanceAccess = null, array $userDataConfig=[]);

    /**
     * Connects instances to a load balancer
     * @param array - of InstanceInterface(s)
     * @param LoadBalancer $loadbalancer
     * @throws Exception - from the platform
     * @return void
     */
    public function connectInstancesToLoadBalancer(array $instances, LoadBalancerInterface $loadBalancer);

    /**
     * Kill/Terminate given running instances
     * @param array - of InstanceInterface(s)
     * @throws Exception - from the platform
     * @return void
     */
    public function killInstances(array $instances);

    /**
     * Finds the latest image that matches the user defined image name
     * @param string $imageName
     * @throws Exception
     * @return ImageInterface
     */
    public function findImage($imageName);
}
