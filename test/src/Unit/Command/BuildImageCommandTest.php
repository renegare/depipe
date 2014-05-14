<?php

namespace App\Test\Command;

use App\Test\Util\ConsoleTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Dumper;

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

        $app->setConfig([
            'base-ami' => 'ami-1234',
            'aws_key' => 'aws_key_123456',
            'aws_secret' => 'aws_secret_123456',
            'aws_region' => 'us-east-1',
            'user_data' => [
                'runcmd' => [
                    ["echo $(date) > running.since"]
                ]
            ]
        ]);

        $ec2Client = $command->getEc2Client();
        $this->assertInstanceOf('Aws\Ec2\Ec2Client', $ec2Client);
        $credentials = $ec2Client->getCredentials();
        $this->assertEquals('aws_key_123456', $credentials->getAccessKeyId());
        $this->assertEquals('aws_secret_123456', $credentials->getSecretKey());
        $this->assertEquals('us-east-1', $ec2Client->getRegion());

        $mockEc2Client = $this->getMockBuilder('Aws\Ec2\Ec2Client')
            ->disableOriginalConstructor()
            ->getMock();
        $command->setEc2Client($mockEc2Client);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);
        $this->assertContains('Build Complete', $commandTester->getDisplay());
    }
}
