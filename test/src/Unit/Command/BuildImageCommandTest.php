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
     * - Given an ami[id]
     * - Given a set of AWS credentials
     * - Given a set of user data
     * - Launch an instance with user data
     * - Snapshot image
     * - Terminate instance
     * - Output pretty json of built ami[id]
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

        $app->setConfig([
            'base-ami' => 'ami-1234abc5',
            'aws_key' => 'aws_key_123456',
            'aws_secret' => 'aws_secret_123456',
            'aws_region' => 'us-east-1',
            'instance_type' => InstanceType::T1_MICRO,
            'user_data' => [
                'runcmd' => [
                    ["echo $(date) > running.since"]
                ]
            ],
            'block_mappings' => $expectedBlockMappings
        ]);

        $ec2Client = $command->getEc2Client();
        $this->assertInstanceOf('Aws\Ec2\Ec2Client', $ec2Client);
        $credentials = $ec2Client->getCredentials();
        $this->assertEquals('aws_key_123456', $credentials->getAccessKeyId());
        $this->assertEquals('aws_secret_123456', $credentials->getSecretKey());
        $this->assertEquals('us-east-1', $ec2Client->getRegion());

        $mockData = json_decode('{"requestId":"1986169a-435c-4862-a023-3fc0344771f3","ReservationId":"r-c93ac3b7","OwnerId":"404046692034","Groups":[{"GroupName":"default","GroupId":"sg-d1b794b8"}],"Instances":[{"InstanceId":"i-0207a351","ImageId":"ami-8997afe0","State":{"Code":"0","Name":"pending"},"PrivateDnsName":"","PublicDnsName":"","StateTransitionReason":"","AmiLaunchIndex":"0","ProductCodes":[],"InstanceType":"t1.micro","LaunchTime":"2014-05-14T21:36:52.000Z","Placement":{"AvailabilityZone":"us-east-1d","GroupName":"","Tenancy":"default"},"KernelId":"aki-88aa75e1","Monitoring":{"State":"disabled"},"StateReason":{"Code":"pending","Message":"pending"},"Architecture":"x86_64","RootDeviceType":"ebs","RootDeviceName":"/dev/sda1","BlockDeviceMappings":[],"VirtualizationType":"paravirtual","ClientToken":"","SecurityGroups":[{"GroupName":"default","GroupId":"sg-d1b794b8"}],"Hypervisor":"xen","NetworkInterfaces":[],"EbsOptimized":false}]}', true);
        $mockResponse = new GuzzleModel($mockData);
        $mockEc2Client = $this->getMockBuilder('Aws\Ec2\Ec2Client')
            ->disableOriginalConstructor()
            ->getMock();
        $mockEc2Client->expects($this->any())
            ->method('__call')
            ->will($this->returnCallback(function($method, $args) use ($expectedBlockMappings, $mockResponse){
                switch($method) {
                    case 'runInstances':
                        $this->assertEquals([
                            "InstanceType" => InstanceType::T1_MICRO,
                            "ImageId" => "ami-1234abc5",
                            "MinCount" => 1,
                            "MaxCount" => 1,
                            'BlockDeviceMappings' => $expectedBlockMappings
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
}
