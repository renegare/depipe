<?php

namespace App\Test\Unit\Platform\Aws;
use Guzzle\Service\Resource\Model as GuzzleModel;
use App\Platform\Aws\Instance;

class InstanceTest extends \PHPUnit_Framework_TestCase {

    public function testProvisionWith() {
        $expectedHost = 'test.instance.com';
        $scripts = ["echo 'script executed!'"];
        $instanceDescription = $this->getGuzzleModelResponse('aws/describe_instances_response')
            ->getPath('Reservations/0/Instances/0');
        $instanceDescription['PublicDnsName'] = $expectedHost;

        $access = $this->getMockBuilder('App\Platform\InstanceAccessInterface')
            ->getMock();
        $access->expects($this->once())
            ->method('connect')
            ->will($this->returnCallback(function($host) use ($expectedHost) {
                $this->assertEquals($expectedHost, $host);
            }));

        $access->expects($this->once())
            ->method('exec')
            ->will($this->returnCallback(function($code) use ($scripts) {
                $this->assertEquals($scripts[0], $code);
            }));

        $instance = new Instance('i-122434', $instanceDescription);
        $instance->provisionWith($access, $scripts);

    }

    public function getGuzzleModelResponse($fileKey) {
        return new GuzzleModel(
            json_decode(
                file_get_contents(sprintf(PROJECT_ROOT . '/test/mock_responses/%s.json', $fileKey)), true));
    }
}
