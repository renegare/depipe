<?php

namespace App\Test\Unit\Platform\Aws;

use App\Platform\Aws\Client;
use App\Platform\Aws\Image;
use App\Platform\Aws\Instance;
use Symfony\Component\Yaml\Dumper;
use Guzzle\Service\Resource\Model as GuzzleModel;

class ClientTest extends \PHPUnit_Framework_TestCase {

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

                return $mockResponse;
            }));


        $client = $this->getMockClient(['getEc2Client']);
        $client->expects($this->once())
            ->method('getEc2Client')
            ->will($this->returnValue($mockEc2Client));
        $instances = $client->launchInstances($image, 1, $instanceConfig, $userDataConfig);

        $this->assertCount(1, $instances);
        $this->assertInstanceof('App\Platform\Aws\Instance', $instances[0]);
    }

    public function testProvisionInstances() {

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
        $response = new GuzzleModel(json_decode(file_get_contents(PROJECT_ROOT . '/test/mock_responses/aws/describe_images_response.json'), true));

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
}
