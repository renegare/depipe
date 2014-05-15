<?php

namespace App\Command;

use App\AbstractCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Aws\Ec2\Ec2Client;
use Symfony\Component\Yaml\Dumper;

class BuildImageCommand extends AbstractCommand
{
    protected $ec2Client;

    protected function configure()
    {
        $this->setName('build')
            ->setDescription('Build an image')
        ;
    }

    protected function doExecute(InputInterface $input) {
        $yamlDumper = new Dumper();
        $client = $this->getEc2Client();
        $response = $client->runInstances([
            'ImageId' => $this->get('base-ami'),
            'InstanceType' => $this->get('instance_type'),
            'MinCount' => 1,
            'MaxCount' => 1,
            'BlockDeviceMappings' => $this->get('block_mappings'),
            'UserData' => base64_encode("#cloud-config\n" . $yamlDumper->dump($this->get('cloud-init'), 2))
        ]);

        $this->debug('runInstances api response', [
            'response' => $response,
            'response_class' => get_class($response)
        ]);

        $instanceIds = $response->getPath('Instances/*/InstanceId');
        $this->info(sprintf('Started Instance %s', $instanceIds[0]), ['instance_id' => $instanceIds]);

        $this->info('Build Complete');
    }

    public function getEc2Client() {
        if(!$this->ec2Client) {
            $ec2Client = Ec2Client::factory([
                'key' => $this->get('aws_key'),
                'secret' => $this->get('aws_secret'),
                'region' => $this->get('aws_region')
            ]);

            $this->ec2Client = $ec2Client;
        }

        return $this->ec2Client;
    }

    public function setEc2Client(Ec2Client $client) {
        $this->ec2Client = $client;
    }
}
