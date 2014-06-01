<?php

namespace App\Test\Unit\Platform\Aws;

use App\Platform\Aws\Client;
use App\Platform\Aws\Image;
use App\Platform\Aws\Instance;
use App\Platform\Aws\LoadBalancer;
use Symfony\Component\Yaml\Dumper;
use Guzzle\Service\Resource\Model as GuzzleModel;

class ClientTest extends \App\Test\Util\BaseTestCase {

    public function testLaunchInstances() {

        $mockImageId = 'ami-123456';
        $instanceConfig = ['AnotherConfig' => '12324'];
        $userDataConfig = ['runcmd' => '...'];

        $image = $this->getMockBuilder('App\Platform\Aws\Image')
            ->disableOriginalConstructor()
            ->getMock();
        $image->expects($this->any())
            ->method('getId')
            ->will($this->returnCallback(function() use ($mockImageId){
                return $mockImageId;
            }));

        $mockEc2Client = $this->getMockEc2Client(['runInstances', 'waitUntilInstanceRunning', 'describeInstances']);
        $mockEc2Client->expects($this->once())
            ->method('runInstances')
            ->will($this->returnCallback(function($config) use ($mockImageId, $instanceConfig, $userDataConfig){
                $yamlDumper = new Dumper();
                $this->assertEquals([
                    'ImageId' => $mockImageId,
                    'MinCount' => 1,
                    'MaxCount' => 1,
                    'UserData' => base64_encode("#cloud-config\n" . $yamlDumper->dump($userDataConfig)),
                    'AnotherConfig' => '12324'
                ], $config);

                return new GuzzleModel([
                        'Instances' => [['InstanceId' => 'i-123456']]
                    ]);
            }));
        $mockEc2Client->expects($this->once())
            ->method('waitUntilInstanceRunning')
            ->will($this->returnCallback(function($config) {
                $this->assertEquals(['InstanceIds' => ['i-123456']], $config);
            }));
        $mockEc2Client->expects($this->once())
            ->method('describeInstances')
            ->will($this->returnCallback(function($config) {
                $this->assertEquals(['InstanceIds' => ['i-123456']], $config);
                return $this->getGuzzleModelResponse('aws/describe_instances_response');
            }));


        $client = $this->getMockClient(['getEc2Client']);
        $client->expects($this->any())
            ->method('getEc2Client')
            ->will($this->returnValue($mockEc2Client));
        $instances = $client->launchInstances($image, 1, $instanceConfig, null, $userDataConfig);

        $this->assertCount(1, $instances);
        $this->assertInstanceof('App\Platform\Aws\Instance', $instances[0]);
    }

    public function testGetEc2Client() {
        $client = new Client();
        $client->setCredentials([
            'region' => 'us-east-1',
            'vendor' => 'aws'
        ]);

        $this->assertInstanceOf('Aws\Ec2\Ec2Client', $client->getEc2Client());
    }

    public function getMockClient(array $methods=[]) {
        $client = $this->getMockBuilder('App\Platform\Aws\Client')
            ->disableOriginalConstructor()
            ->setMethods($methods)
            ->getMock()
        ;

        return $client;
    }

    public function getMockEc2Client(array $methods=[]) {
        $client = $this->getMockBuilder('Aws\Ec2\Ec2Client')
            ->disableOriginalConstructor()
            ->setMethods($methods)
            ->getMock()
        ;

        return $client;
    }

    public function testConvertToImage() {
        $callCount = 0;
        $client = new Client();
        $client->setCredentials(['sleep.interval' => 0]);

        $this->patchClassMethod('App\Platform\Aws\Client::getEc2Client', function() use (&$callCount){
            $mockEc2Client = $this->getMockEc2Client(['describeImages']);
            $mockEc2Client->expects($this->exactly(4))
                ->method('describeImages')
                ->will($this->returnCallback(function($request) use (&$callCount){
                    $this->assertEquals([
                        'ImageIds' => ['ami-123456']
                    ], $request);
                    ++$callCount;
                    if($callCount < 4) {
                        return $this->getGuzzleModelResponse('aws/describe_images_response_sate_not_available');
                    } else {
                        return $this->getGuzzleModelResponse('aws/describe_images_response');
                    }
                }));
            return $mockEc2Client;
        }, 1);

        $image = $client->convertToImage('ami-123456');
        $this->assertInstanceof('App\Platform\Aws\Image', $image);
        $this->assertEquals('ami-123456', $image->getId());
    }

    public function testSnapshotInstance() {

        $this->patchClassMethod('App\Platform\Aws\Client::getEc2Client', function(){
            $client = $this->getMockEc2Client(['createImage']);
            $client->expects($this->once())
                ->method('createImage')
                ->will($this->returnCallback(function($config) {
                    $this->assertEquals([
                        'InstanceId' => 'i-12223',
                        'Name' => 'web.app'], $config);
                    return $this->getGuzzleModelResponse('aws/create_image_response');
                }));
            return $client;
        }, 1);

        $mockImage = new Image('ami-999999', []);
        $this->patchClassMethod('App\Platform\Aws\Client::convertToImage', function($imageId) use ($mockImage){
            $this->assertEquals('ami-999999', $imageId);
            return $mockImage;
        }, 1);

        $mockInstance = new Instance('i-12223', []);
        $client = new Client();
        $this->assertSame($mockImage, $client->snapShotInstance($mockInstance, 'web.app'));
    }

    public function testKillInstances() {

        $this->patchClassMethod('App\Platform\Aws\Client::getEc2Client', function(){
            $client = $this->getMockEc2Client(['terminateInstances']);
            $client->expects($this->once())
                ->method('terminateInstances')
                ->will($this->returnCallback(function($config) {
                    $this->assertEquals([
                        'InstanceIds' => ['i-12223']], $config);
                    return $this->getGuzzleModelResponse('aws/null_response');
                }));
            return $client;
        }, 1);

        $mockInstance = new Instance('i-12223', []);
        $client = new Client();
        $client->killInstances([$mockInstance]);
    }

    public function getGuzzleModelResponse($fileKey) {
        return new GuzzleModel(
            json_decode(
                file_get_contents(sprintf(PROJECT_ROOT . '/test/mock_responses/%s.json', $fileKey)), true));
    }

    /**
     * at times api calls require the system to wait until an instance/image
     * is available. To do so repeated calls are made to ascertain the current
     * state of things. Given some apis have a rate limit it would be best
     * in such cases to halt/sleep the process for a predefined period time before
     * retrying. This test verifies that sleep period (in seconds) is customisable
     */
    public function testConfigurableSleepTime() {
        $sleepTime = 1;
        $callCount = 0;
        $client = new Client();
        $client->setCredentials(['sleep.interval' => $sleepTime]);

        $this->patchClassMethod('App\Platform\Aws\Client::getEc2Client', function() use (&$callCount){
            $mockEc2Client = $this->getMockEc2Client(['describeImages']);
            $mockEc2Client->expects($this->any())
                ->method('describeImages')
                ->will($this->returnCallback(function() use (&$callCount){
                    ++$callCount;
                    if($callCount < 3) {
                        return $this->getGuzzleModelResponse('aws/describe_images_response_sate_not_available');
                    } else {
                        return $this->getGuzzleModelResponse('aws/describe_images_response');
                    }
                }));
            return $mockEc2Client;
        }, 1);

        $start = time();
        $client->convertToImage('ami-123456');
        $this->assertEquals($sleepTime * ($callCount-1), time() - $start);
    }

    public function testFindImage() {
        $client = new Client();

        $this->patchClassMethod('App\Platform\Aws\Client::getEc2Client', function() use (&$callCount){
            $mockEc2Client = $this->getMockEc2Client(['describeImages']);
            $mockEc2Client->expects($this->any())
                ->method('describeImages')
                ->will($this->returnCallback(function($config){
                    $this->assertEquals([
                        'Filters' => [
                            [
                                'Name' => 'name',
                                'Values' => ['Test Image Name']]]], $config);
                    return $this->getGuzzleModelResponse('aws/describe_images_response');
                }));
            return $mockEc2Client;
        }, 1);

        $image = $client->findImage('Test Image Name');
        $this->assertInstanceOf('App\Platform\ImageInterface', $image);
    }

    public function testConvertToLoadBalancer() {
        $this->patchClassMethod('App\Platform\Aws\Client::getELBClient', function(){
            $client = $this->getMockBuilder('Aws\ElasticLoadBalancing\ElasticLoadBalancingClient')
                ->setMethods(['describeLoadBalancers'])
                ->disableOriginalConstructor()
                ->getMock();
            $client->expects($this->once())
                ->method('describeLoadBalancers')
                ->will($this->returnCallback(function($config) {
                    $this->assertEquals([
                        'LoadBalancerNames' => ['elb-test-name']], $config);
                    $model = $this->getGuzzleModelResponse('aws/describe_load_balancers_response');
                    $model->setPath('LoadBalancerDescriptions/0/LoadBalancerName', 'elb-test-name');
                    return $model;
                }));
            return $client;
        }, 1);

        $client = new Client();
        $elb = $client->convertToLoadBalancer('elb-test-name');
        $this->assertInstanceOf('App\Platform\LoadBalancerInterface', $elb);
        $this->assertEquals('elb-test-name', (string) $elb);
    }

    public function testConnectInstancesToLoadBalancer() {
        $loadBalancer = new LoadBalancer('elb-test', []);
        $instances = [new Instance('i-test1', []), new Instance('i-test2', [])];

        $this->patchClassMethod('App\Platform\Aws\Client::getELBClient', function(){
            $client = $this->getMockBuilder('Aws\ElasticLoadBalancing\ElasticLoadBalancingClient')
                ->setMethods(['registerInstancesWithLoadBalancer', 'describeInstanceHealth'])
                ->disableOriginalConstructor()
                ->getMock();

            $client->expects($this->once())
                ->method('registerInstancesWithLoadBalancer')
                ->will($this->returnCallback(function($config) {
                    $this->assertEquals([
                        'LoadBalancerName' => 'elb-test',
                        'Instances' => [
                            ['InstanceId' => 'i-test1'],
                            ['InstanceId' => 'i-test2']]], $config);
                    return $this->getGuzzleModelResponse('aws/null_response');
                }));

            $count = 0;
            $instancesHealth = [
                'i-test1' => false,
                'i-test2' => false
            ];

            $client->expects($this->exactly(5))
                ->method('describeInstanceHealth')
                ->will($this->returnCallback(function($config) use(&$count, &$instancesHealth){

                    $this->assertEquals([
                        'LoadBalancerName' => 'elb-test',
                        'Instances' => [
                            ['InstanceId' => 'i-test1'],
                            ['InstanceId' => 'i-test2']]], $config);

                    switch($count) {
                        case 2:
                            $instancesHealth['i-test2'] = true;
                            break;
                        case 4:
                            $instancesHealth['i-test1'] = true;
                            break;
                    }
                    ++$count;

                    $model = $this->getGuzzleModelResponse('aws/describe_instance_health_response');
                    $states = [];
                    foreach($config['Instances'] as $instance) {
                        $id = $instance['InstanceId'];
                        $instance['State'] = $instancesHealth[$id]? 'InService' : 'OutOfService';
                        $states[] = $instance;
                    }
                    $model->setPath('InstanceStates', $states);
                    return $model;
                }));
            return $client;
        }, 1);

        $client = new Client();
        $client->setCredentials(['sleep.interval' => 0]);
        $elb = $client->connectInstancesToLoadBalancer($instances, $loadBalancer);
    }
}
