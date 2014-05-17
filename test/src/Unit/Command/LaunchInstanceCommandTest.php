<?php

namespace App\Test\Command;

use App\Test\Util\ConsoleTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class LaunchInstancesCommandTest extends ConsoleTestCase {

    /**
     * Assert that the command launches an instance running the following tasks:
     * - Given I have a base_image
     * - AND I have platform credentials
     * - AND I have userdata_config
     * - AND I have provisioning shell_scripts
     * - AND I have instance_config
     * - AND I have instance_count
     *
     * - RUN get_client task: [credentials] : client
     * - RUN launch_instances task: [client, userdata_config, base_image, instance_config, instance_count] : [instance(s)]
     * - RUN provision_instances task: [client, [instance(s)], shell_scripts] : void
     */
    public function testExecution() {
        $app = $this->getApplication();
        $command = $app->find('pipe:launch');
        $this->assertInstanceOf('App\Command\LaunchInstancesCommand', $command);

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
            'instance_count' => 1
        ];

        $app->setConfig($expectedConfig);

        $mockClient = $this->getMock('App\Client');
        $this->mockTask('get_client', $command,
            ['credentials' => $expectedConfig['credentials']], $mockClient);

        $mockInstance = $this->getMock('App\Instance');
        $this->mockTask('launch_instances', $command, [
            'client' => $mockClient,
            'base_image' => $expectedConfig['base_image'],
            'userdata_config' => $expectedConfig['userdata_config'],
            'instance_config' => $expectedConfig['instance_config']], [$mockInstance]);

        $this->mockTask('provision_instances', $command, [
            'client' => $mockClient,
            'instance' => [$mockInstance],
            'shell_scripts' => $expectedConfig['shell_scripts']]);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);
        $this->assertContains('Launched 1 instance(s)', $commandTester->getDisplay());
    }
}
