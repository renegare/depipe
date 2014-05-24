<?php

namespace App\Test\Unit\Util\InstanceAccess;

use App\Util\InstanceAccess\SSHAccess;

class SSHAccessTest extends \App\Test\Util\BaseTestCase {

    public function testConnect() {
        $self = $this;
        $expectedHost = 'test.somewhere.com';
        $expectedMaxAttempts = 5;

        $this->patchClassMethod('Net_SSH2::disconnect');

        $this->patchClassMethod('Net_SSH2::Net_SSH2', function($host, $port, $timeout) use ($expectedHost, &$expectedMaxAttempts){
            $this->assertEquals($expectedHost, $host);
            $this->assertEquals(22, $port);
            $this->assertEquals(1, $timeout);

            if($expectedMaxAttempts > 1) {
                --$expectedMaxAttempts;
                throw new \Exception('Cannot connect to  ...');
            }
        });

        $this->patchClassMethod('Net_SSH2::login', function($user, $password) use ($self, &$expectedMaxAttempts){
            $this->assertEquals('root', $user);
            $this->assertEquals('test', $password);
            $this->assertEquals(1, $expectedMaxAttempts);
        }, 1);

        $access = new SSHAccess();
        $access->setCredentials([
            'user' => 'root',
            'password' => 'test',
            'connect.attempts' => $expectedMaxAttempts,
            'connect.sleep' => 5,
        ]);

        $access->connect($expectedHost);
    }

    public function testExec() {
        $this->patchClassMethod('Net_SSH2::disconnect');
        $this->patchClassMethod('Net_SSH2::Net_SSH2');
        $this->patchClassMethod('Net_SSH2::login');

        $constructorCalled = false;
        $this->patchClassMethod('Net_SFTP::Net_SFTP', function($host, $port, $timeout) use (&$constructorCalled){
            $this->assertEquals('test.somewhere.com', $host);
            $this->assertEquals(22, $port);
            $this->assertEquals(1, $timeout);
            $constructorCalled = true;
        });

        $this->patchClassMethod('Net_SFTP::login', function($user, $password) use (&$expectedMaxAttempts, &$constructorCalled){
            $this->assertTrue($constructorCalled);
            $this->assertEquals('root', $user);
            $this->assertEquals('test', $password);
        }, 1);

        $this->patchClassMethod('Net_SFTP::put', function($remotePath, $code) {
            $this->assertEquals('/tmp/execute.sh', $remotePath);
            $this->assertEquals("#!/bin/bash\ndate", $code);
        }, 1);

        $this->patchClassMethod('Net_SFTP::chmod', function($premissions, $remotePath) {
            $this->assertEquals('/tmp/execute.sh', $remotePath);
            $this->assertEquals(0550, $premissions);
        }, 1);

        $mockCallback = function(){};
        $this->patchClassMethod('Net_SSH2::exec', function($command, $cb) use ($mockCallback){
            $this->assertEquals('/tmp/execute.sh', $command);
            $this->assertEquals($mockCallback, $cb);
        }, 1);

        $this->patchClassMethod('Net_SSH2::exec', function($command, $cb) use ($mockCallback){
            $this->assertEquals('/tmp/execute.sh', $command);
            $this->assertEquals($mockCallback, $cb);
        }, 1);

        $access = new SSHAccess();
        $access->setCredentials([
            'user' => 'root',
            'password' => 'test'
        ]);

        $access->connect('test.somewhere.com');
        $access->exec("#!/bin/bash\ndate", $mockCallback);
    }

    public function testConnectWithSSHKey() {
        $expectedHost = 'test.somewhere.com';

        $this->patchClassMethod('Net_SSH2::disconnect');
        $this->patchClassMethod('Net_SSH2::Net_SSH2');

        $this->patchClassMethod('Net_SSH2::login', function($user, $password) use (&$expectedMaxAttempts){
            $this->assertEquals('root', $user);
            $this->assertInstanceof('Crypt_RSA', $password);
        }, 1);

        $this->patchClassMethod('Crypt_RSA::loadKey', function($key) {
            $this->assertEquals('--key--123456--key--', $key);
        }, 1);

        $access = new SSHAccess();
        $access->setCredentials([
            'user' => 'root',
            'key' => '--key--123456--key--',
            'connect.sleep' => 5,
        ]);

        $access->connect($expectedHost);
    }

    public function testExecWithSSHKey() {
        $expectedHost = 'test.somewhere.com';

        $this->patchClassMethod('Net_SSH2::disconnect');
        $this->patchClassMethod('Net_SSH2::Net_SSH2');
        $this->patchClassMethod('Net_SSH2::login');
        $this->patchClassMethod('Net_SFTP::Net_SFTP');
        $this->patchClassMethod('Net_SFTP::put');
        $this->patchClassMethod('Net_SFTP::chmod');
        $this->patchClassMethod('Net_SSH2::exec');

        $this->patchClassMethod('Crypt_RSA::loadKey', function($key) {
            $this->assertEquals('--key--123456--key--', $key);
        }, 2);

        $this->patchClassMethod('Net_SFTP::login', function($user, $password) {
            $this->assertEquals('root', $user);
            $this->assertInstanceof('Crypt_RSA', $password);
        }, 1);

        $access = new SSHAccess();
        $access->setCredentials([
            'user' => 'root',
            'key' => '--key--123456--key--'
        ]);

        $access->connect('test.somewhere.com');
        $access->exec("#!/bin/bash\ndate");
    }
}
