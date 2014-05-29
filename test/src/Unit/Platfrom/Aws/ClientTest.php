<?php

namespace App\Test\Unit\Platform\Aws;

use App\Platform\Aws\Client;
use App\Platform\Aws\Image;
use App\Platform\Aws\Instance;
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
        $instances = $client->launchInstances($image, 1, $instanceConfig, $userDataConfig);

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
        $response = $this->getGuzzleModelResponse('aws/describe_images_response');

        $mockEc2Client = $this->getMockEc2Client(['describeImages']);
        $mockEc2Client->expects($this->once())
            ->method('describeImages')
            ->will($this->returnCallback(function($request) use ($response){
                $this->assertEquals([
                    'ImageIds' => ['ami-123456']
                ], $request);

                return $response;
            }));

        $client = $this->getMockClient(['getEc2Client']);
        $client->expects($this->once())
            ->method('getEc2Client')
            ->will($this->returnValue($mockEc2Client));
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
}
