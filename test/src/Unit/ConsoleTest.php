<?php

namespace App\Test\Command;

use App\Test\Util\ConsoleTestCase;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Yaml\Dumper;
use Patchwork as P;

class ConsoleTest extends ConsoleTestCase {

    public function teardown() {
        @unlink('depipe-mock.yml');
    }

    /**
     * assert --log|-l option
     */
    public function testLogOption() {
        $expectedLog = PROJECT_ROOT . '/depipe.log';
        @unlink($expectedLog);
        $app = $this->getApplication();
        $this->assertInstanceOf('App\Console', $app);

        $app->setAutoExit(false);

        $command = $this->getMockForAbstractClass('App\Command', ['doExecute', 'configure'], '', true);
        $command->expects($this->once())
            ->method('doExecute')
            ->will($this->returnCallback(function() use ($command){
                $command->info('test log!');
            }));
        $command->setName('test');
        $app->add($command);

        $appTester = new ApplicationTester($app);
        $appTester->run([
            'command' => $command->getName(),
            '-l' => $expectedLog
        ]);

        $this->assertTrue(file_exists($expectedLog));
        $this->assertContains('test log!', file_get_contents($expectedLog));
        @unlink($expectedLog);
    }

    /**
     * assert --config|-c option
     */
    public function testConfigOption() {
        @unlink('depipe-mock.yml');
        $dumper = new Dumper();
        file_put_contents('depipe-mock.yml', $dumper->dump([
            'parameters' =>[
                'base-ami' => 'ami-test']]));

        $app = $this->getApplication();
        $app->setAutoExit(false);

        $appTester = new ApplicationTester($app);
        $appTester->run([
            '--config' => 'depipe-mock.yml'
        ]);

        $this->assertEquals(['base-ami' => 'ami-test'], $app->getConfig());
        @unlink('depipe-mock.yml');
    }

    /**
     * test we can retrieve parans from the evironment
     */
    public function testParamsFromEnvPlaceholder() {
        @unlink('depipe-mock.yml');
        $time = time();
        putenv(sprintf('DEPIPE_TEST_ENV=%s', $time));

        $dumper = new Dumper();
        file_put_contents('depipe-mock.yml', $dumper->dump([
            'parameters' =>[
                'string' => 'secret-{{env DEPIPE_TEST_ENV }}',
                'literal' => '{{env DEPIPE_TEST_ENV }}']]));

        $app = $this->getApplication();
        $app->setAutoExit(false);

        $appTester = new ApplicationTester($app);
        $appTester->run([
            '--config' => 'depipe-mock.yml'
        ]);

        $this->assertEquals([
            'string' => 'secret-' . $time,
            'literal' => $time], $app->getConfig());
        @unlink('depipe-mock.yml');
        putenv('DEPIPE_TEST_ENV');
    }

    /**
     * @expectedException BadFunctionCallException
     */
    public function testInvalidPlaceholder() {
        @unlink('depipe-mock.yml');

        $dumper = new Dumper();
        file_put_contents('depipe-mock.yml', $dumper->dump([
            'parameters' =>[
                'key' => '{{invalid ... }}']]));

        $app = $this->getApplication();
        $app->setAutoExit(false);

        $appTester = new ApplicationTester($app);
        $appTester->run([
            '--config' => 'depipe-mock.yml'
        ]);
    }

    public function testGetClient() {
        P\replace('App\Test\Util\Mock\PlatformClient::setCredentials', function($config){
            $this->assertEquals([
                'secret' => 'secret-123456',
                'vendor' => 'App\Test\Util\Mock\PlatformClient'
            ], $config);
        });

        $app = $this->getApplication();
        $app->setConfig([
            'credentials' => [
                'secret' => 'secret-123456',
                'vendor' => 'App\Test\Util\Mock\PlatformClient'
            ]
        ]);
    }
}
