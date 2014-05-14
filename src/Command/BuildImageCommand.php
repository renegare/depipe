<?php

namespace App\Command;

use App\AbstractCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Aws\Ec2\Ec2Client;

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
        $this->getEc2Client();
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
