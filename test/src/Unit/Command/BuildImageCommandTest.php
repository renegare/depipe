<?php

namespace App\Test\Command;

use App\Test\Util\ConsoleTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Dumper;
use Aws\Ec2\Enum\InstanceType;
use Guzzle\Service\Resource\Model as GuzzleModel;

class BuildImageCommandTest extends ConsoleTestCase {

    /**
     * Assert that the command builds an image running the following tasks:
     * - Given I have a base_image
     * - AND I have platform credentials
     * - AND I have userdata
     * - AND I have provisioning shell_scripts
     * - AND I have instance_config
     * - AND I have image_name
     *
     * - RUN get_client task: [credentials] : client
     * - RUN launch_instance task: [client, userdata_config, base_image, instance_config] : instance
     * - RUN provision_instance task: [client, instance, shell_scripts] : void
     * - RUN snapshot_instance task: [client, instance, image_name] : new_base_image
     * - RUN terminate_instance: [client, instance] : void
     * - RUN get_artifacts task: [new_base_image] : json
     */
    public function testExecution() {
        $app = $this->getApplication();
        $command = $app->find('pipe:build');
        $this->assertInstanceOf('App\Command\BuildImageCommand', $command);

        $expectedConfig = [
            'base_image' => 'image-1234abc5',
            'credentials' => [
                'aws_key' => 'depipe_key_123456',
                'aws_secret' => 'depipe_secret_123456',
                'vendor' => 'depipe'
            ],
            'userdata' => [
                'runcmd' => [
                    "echo $(date) > running.since"]],
            'shell_scripts' => ['dumm_script.sh'],
            'instance_config' =>[
                'region' => 'north-mars-1',
                'size' => 'insignificant'
            ],
            'image_name' => 'new-base-image'
        ];

        $app->setConfig($expectedConfig);

        $mockClient = $this->getMock('App\Client');
        $this->mockTask('get_client', $command,
            ['credentials' => $expectedConfig['credentials']], $mockClient);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);
        $this->assertContains('Build Complete', $commandTester->getDisplay());



        return;
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
    }

    public function getMockResponse($name) {
        $json = file_get_contents(sprintf('%s/test/mock_responses/%s.json', PROJECT_ROOT, $name));
        return new GuzzleModel(json_decode($json, true));
    }
}
