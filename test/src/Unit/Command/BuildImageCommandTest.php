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
     * - AND I have userdata_config
     * - AND I have provisioning shell_scripts
     * - AND I have instance_config
     * - AND I have image_name
     *
     * - RUN get_client task: [credentials] : client
     * - RUN launch_instance task: [client, userdata_config, base_image, instance_config] : instance
     * - RUN provision_instance task: [client, instance, shell_scripts] : void
     * - RUN snapshot_instance task: [client, instance, new_image] : new_image
     * - RUN terminate_instance: [client, instance] : void
     * - RUN get_artifacts task: [new_image] : serializable array of data | string
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
            'userdata_config' => [
                'runcmd' => [
                    "echo $(date) > running.since"]],
            'shell_scripts' => ['dumm_script.sh'],
            'instance_config' =>[
                'region' => 'north-mars-1',
                'size' => 'insignificant'
            ],
            'new_image' => 'new-base-image'
        ];

        $app->setConfig($expectedConfig);

        $mockClient = $this->getMock('App\Client');
        $this->mockTask('get_client', $command,
            ['credentials' => $expectedConfig['credentials']], $mockClient);

        $mockInstance = $this->getMock('App\Instance');
        $this->mockTask('launch_instance', $command, [
            'client' => $mockClient,
            'base_image' => $expectedConfig['base_image'],
            'userdata_config' => $expectedConfig['userdata_config'],
            'instance_config' => $expectedConfig['instance_config']], $mockInstance);

        $this->mockTask('provision_instance', $command, [
            'client' => $mockClient,
            'instance' => $mockInstance,
            'shell_scripts' => $expectedConfig['shell_scripts']]);

        $mockNewImage = $this->getMock('App\Image');
        $this->mockTask('snapshot_instance', $command, [
            'client' => $mockClient,
            'instance' => $mockInstance,
            'new_image' => $expectedConfig['new_image']], $mockNewImage);

        $this->mockTask('terminate_instance', $command, [
            'client' => $mockClient,
            'instance' => $mockInstance]);

        $this->mockTask('get_artifacts', $command, [
            'image' => $mockNewImage], ['image' => $mockNewImage]);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);
        $this->assertContains('Build Complete', $commandTester->getDisplay());
    }

    public function getMockResponse($name) {
        $json = file_get_contents(sprintf('%s/test/mock_responses/%s.json', PROJECT_ROOT, $name));
        return new GuzzleModel(json_decode($json, true));
    }
}
