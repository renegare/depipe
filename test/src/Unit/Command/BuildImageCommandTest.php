<?php

namespace App\Test\Command;

use App\Test\Util\ConsoleTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Dumper;
use Aws\Ec2\Enum\InstanceType;
use Guzzle\Service\Resource\Model as GuzzleModel;

class BuildImageCommandTest extends ConsoleTestCase {

    /**
     * Assert that the command builds an image with following flow
     * - Given I have an ami-[id] (that has been properly pre-configured with cloud-init)
     * - And I have a set of AWS credentials
     * - And I have user data config
     * - When I launch an instance with user data config
     * - Then I ssh into instance
     * - And I run registered shell provisioner scripts
     * - And I Snapshot image
     * - And Terminate instance
     * - And I expect to get [pretty] json containing a built ami[id]
     */
    public function testExecution() {
        $app = $this->getApplication();
        $command = $app->find('pipe:build');
        $this->assertInstanceOf('App\Command\BuildImageCommand', $command);

        $expectedBlockMappings = [[
            'DeviceName' => '/dev/sda',
            'Ebs' => [
                'VolumeSize' => 10,
                'DeleteOnTermination' => true]]];
        $expectedCloudInit = [
            'runcmd' => [
                "echo $(date) > running.since"]];

        $app->setConfig([
            'base-ami' => 'ami-1234abc5',
            'aws_key' => 'aws_key_123456',
            'aws_secret' => 'aws_secret_123456',
            'aws_region' => 'us-east-1',
            'instance_type' => InstanceType::T1_MICRO,
            'cloud-init' => $expectedCloudInit,
            'block_mappings' => $expectedBlockMappings
        ]);

        $ec2Client = $command->getEc2Client();
        $this->assertInstanceOf('Aws\Ec2\Ec2Client', $ec2Client);
        $credentials = $ec2Client->getCredentials();
        $this->assertEquals('aws_key_123456', $credentials->getAccessKeyId());
        $this->assertEquals('aws_secret_123456', $credentials->getSecretKey());
        $this->assertEquals('us-east-1', $ec2Client->getRegion());

        $mockResponse = $this->getMockResponse('aws/run_instances_single');
        $mockEc2Client = $this->getMockBuilder('Aws\Ec2\Ec2Client')
            ->disableOriginalConstructor()
            ->getMock();
        $mockEc2Client->expects($this->any())
            ->method('__call')
            ->will($this->returnCallback(function($method, $args) use ($expectedBlockMappings, $mockResponse, $expectedCloudInit){
                switch($method) {
                    case 'runInstances':
                        $this->assertEquals([
                            "InstanceType" => InstanceType::T1_MICRO,
                            "ImageId" => "ami-1234abc5",
                            "MinCount" => 1,
                            "MaxCount" => 1,
                            'BlockDeviceMappings' => $expectedBlockMappings,
                            'UserData' => base64_encode('#cloud-config
runcmd:
    - \'echo $(date) > running.since\'
')
                        ], $args[0]);
                        break;
                    default:
                        throw new \Exception('Unexpected method call: ' . $method);
                        break;
                }

                return $mockResponse;
            }));
        $command->setEc2Client($mockEc2Client);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);
        $this->assertContains('Build Complete', $commandTester->getDisplay());
    }

    public function getMockResponse($name) {
        $json = file_get_contents(sprintf('%s/test/mock_responses/%s.json', PROJECT_ROOT, $name));
        return new GuzzleModel(json_decode($json, true));
    }
}
