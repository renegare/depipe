<?php

namespace App\Test\Unit\Util\InstanceAccess;

use App\Util\InstanceAccess\SSHAccess;
use Patchwork as P;

class SSHAccessTest extends \PHPUnit_Framework_TestCase {

    public function testConnect() {
        $self = $this;
        $expectedHost = 'test.somewhere.com';
        $expectedMaxAttempts = 5;

        $this->patchClassMethod('Net_SSH2::disconnect');

        $this->patchClassMethod('Net_SSH2::Net_SSH2', function($host, $port, $timeout) use ($expectedHost, &$expectedMaxAttempts){
            $this->assertEquals($expectedHost, $host);
            $this->assertEquals(22, $port);
            $this->assertEquals(0, $timeout);

            if($expectedMaxAttempts > 1) {
                --$expectedMaxAttempts;
                throw new \Exception('Cannot connect to  ...');
            }

        }, $expectedMaxAttempts);


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

    public function patchClassMethod($patchTarget, \Closure $patch=null, $expectedCallCount=null) {
        list($class, $method) = explode('::', $patchTarget);
        $className = explode('\\', $class);
        $className = array_pop($className);
        $isConstructor = false;

        if($method === '__construct' || $className === $method) {
            $isConstructor = true;
            $expectedCallCount = -1;
        }

        $expectedCallCount = $expectedCallCount > -1 ? $this->exactly($expectedCallCount) : $this->any();

        $mock = $this->getMockBuilder($class)
            ->disableOriginalConstructor()
            ->setMethods([$method])
            ->getMock();

        $mock->expects($expectedCallCount)
            ->method($method);
        P\replace($patchTarget, function() use ($patch, $mock, $method, $isConstructor){
            $args = func_get_args();
            if(!$isConstructor) {
                call_user_func_array([$mock, $method], $args);
            }
            $args[] = $this;
            if($patch) {
                return call_user_func_array($patch, $args);
            }
        });
    }
}

class MockStub {
    public function doSomething() {}
}
