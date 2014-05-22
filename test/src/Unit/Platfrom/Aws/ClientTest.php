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

    public function testProvisionInstances() {
        $mockLocalScript = PROJECT_ROOT . '/test/resources/dummy_ssh.sh';

        $mockInstance = new Instance('i-122345', $this->getGuzzleModelResponse('aws/describe_instances_response')
            ->toArray()['Reservations'][0]['Instances'][0]);

        $mockSSHClient = $this->getMockBuilder('App\Util\SSHClient')
            ->getMock();

        $mockSSHClient->expects($this->any())
            ->method('connect')
            ->will($this->returnCallback(function($user, $host, $privateKey){
                
            }));

        $mockSSHClient->expects($this->at(2))
            ->method('connect')
            ->will($this->returnCallback(function($user, $host, $privateKey){
                $this->assertEquals('root', $user);
                $this->assertNull($privateKey);
                return true;
            }));
        $mockSSHClient->expects($this->once())
            ->method('executeShellScript')
            ->will($this->returnCallback(function($localPath) use ($mockLocalScript){
                $this->assertEquals($mockLocalScript, $localPath);
                return true;
            }));

        $client = $this->getMockClient(['getEc2Client']);
        $client->setSSHClient($mockSSHClient);

        $client->provisionInstances([$mockInstance], [$mockLocalScript]);
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

    public function getGuzzleModelResponse($fileKey) {
        return new GuzzleModel(
            json_decode(
                file_get_contents(sprintf(PROJECT_ROOT . '/test/mock_responses/%s.json', $fileKey)), true));
    }
}
