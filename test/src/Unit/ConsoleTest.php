<?php

namespace App\Test\Command;

use App\Test\Util\ConsoleTestCase;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Yaml\Dumper;

class ConsoleTest extends ConsoleTestCase {

    public function teardown() {
        parent::teardown();
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
     * test we can retrieve parans from the evironment
     */
    public function testParamsFromTimePlaceholder() {
        @unlink('depipe-mock.yml');
        $time = time();
        putenv(sprintf('DEPIPE_TEST_ENV=%s', $time));

        $dumper = new Dumper();
        file_put_contents('depipe-mock.yml', $dumper->dump([
            'parameters' =>[
                'string' => '{{time}}',
                'string2' => '{{time Ymd}}',
                'string3' => 'image.name.{{time}}',
                'array' => [
                    '{{time}}',
                    '{{time Ymd}}',
                    'image.name.{{time Ymd}}']]]));

        $app = $this->getApplication();
        $app->setAutoExit(false);

        $appTester = new ApplicationTester($app);
        $appTester->run([
            '--config' => 'depipe-mock.yml'
        ]);

        $time = time();
        $formattedTime = date('Ymd');
        $this->assertEquals([
            'string' => $time,
            'string2' => $formattedTime,
            'string3' => 'image.name.'.$time,
            'array' => [
                $time,
                $formattedTime,
                'image.name.'.$formattedTime]], $app->getConfig());

        @unlink('depipe-mock.yml');
        putenv('DEPIPE_TEST_ENV');
    }

    /**
     * test we can retrieve value for a param from a file
     * (as in the whole contents of the file will be the value of the param)
     */
    public function testParamsFromFilePlaceholder() {
        @unlink('depipe-mock.yml');

        $app = $this->getApplication();
        $app->setAutoExit(false);
        $appTester = new ApplicationTester($app);

        $time = time();
        $dumper = new Dumper();

        file_put_contents('depipe-mock.yml', $dumper->dump([
            'parameters' =>[
                'json_string' => '{{file composer.json }}',
                'string' => '{{file build.sh }}',
                'embedded_string' => 'echo "{{file build.sh }}" > /tmp/build.sh',
                'array' => ['{{file build.sh }}', 'echo "{{file build.sh }}" > /tmp/build.sh']]]));
        $appTester->run([
            '--config' => 'depipe-mock.yml'
        ]);
        $fileContents = file_get_contents('build.sh');
        $jsonFileContents = file_get_contents('composer.json');
        $this->assertEquals([
            'json_string' => $jsonFileContents,
            'string' => $fileContents,
            'embedded_string' => 'echo "' . $fileContents . '" > /tmp/build.sh',
            'array' => [$fileContents, 'echo "' . $fileContents . '" > /tmp/build.sh']
        ], $app->getConfig());

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
        $self = $this;
        $this->patchClassMethod('App\Platform\Aws\Client::setCredentials', function($config) use ($self){
            $self->assertEquals([
                'secret' => 'secret-123456',
                'class' => 'App\Platform\Aws\Client'
            ], $config);
        }, 1);

        $app = $this->getApplication();
        $app->setConfig([
            'credentials' => [
                'secret' => 'secret-123456',
                'class' => 'App\Platform\Aws\Client'
            ]
        ]);

        $client = $app->getClient();
        $this->assertInstanceOf('App\Platform\ClientInterface', $client);
    }

    public function testGetInstanceAccess() {
        $self = $this;
        $this->patchClassMethod('App\Util\InstanceAccess\SSHAccess::setCredentials', function($config) use ($self){
            $self->assertEquals([
                'root' => 'secret-123456',
                'password' => 'pw',
                'private.key' => 'pk'
            ], $config);
        }, 1);

        $app = $this->getApplication();
        $app->setConfig([
            'instance.access' => [
                'root' => 'secret-123456',
                'password' => 'pw',
                'private.key' => 'pk'
            ]
        ]);

        $access = $app->getInstanceAccess();
        $this->assertInstanceOf('App\Platform\InstanceAccessInterface', $access);
    }

    public function testGetInstanceAccessOfSpecifiedClas() {
        $app = $this->getApplication();
        $app->setConfig([
            'instance.access' => [
                'class' => 'App\Platform\Aws\InstanceAccess'
            ]]);

        $access = $app->getInstanceAccess();
        $this->assertInstanceOf('App\Platform\Aws\InstanceAccess', $access);
    }

    /**
     * assert pipeline param in config is accessible via getPipeLine
     */
    public function testGetPipeLine() {
        @unlink('depipe-mock.yml');
        $dumper = new Dumper();
        file_put_contents('depipe-mock.yml', $dumper->dump([
            'pipeline' =>[
                'base-ami' => 'ami-test']]));

        $app = $this->getApplication();
        $app->setAutoExit(false);

        $appTester = new ApplicationTester($app);
        $appTester->run([
            '--config' => 'depipe-mock.yml'
        ]);

        $this->assertEquals(['base-ami' => 'ami-test'], $app->getPipeLine());
        @unlink('depipe-mock.yml');
    }

    public function testAppendConfigReplacesInstanceAccess() {
        $app = $this->getApplication();
        $app->setConfig([
            'instance.access' => [
                'class' => 'App\Platform\Aws\InstanceAccess'
            ]]);

        $access1 = $app->getInstanceAccess();
        $this->assertInstanceOf('App\Platform\Aws\InstanceAccess', $access1);

        $app->appendConfig([
            'instance.access' => [
                'class' => 'App\Platform\Aws\InstanceAccess'
            ]]);

        $access2 = $app->getInstanceAccess();
        $this->assertInstanceOf('App\Platform\Aws\InstanceAccess', $access2);

        $this->assertNotSame($access1, $access2);
    }
}
