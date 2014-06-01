<?php

namespace App\Test\Util;

use Patchwork as P;

abstract class BaseTestCase extends \PHPUnit_Framework_TestCase {

    public function teardown() {
        P\undoAll();
    }

    /**
     * Uses Patchwork\replace to override a class::method. Also uses PHPUnit_Mock* to
     * assert the method has been called. However it does not apply call assertion to
     * constructor methods (some complications).
     * @param string $patchTarget - class::method to override
     * @param mixed $patch - optional value|callback that will be returned|called in place of the method (else does nothing)
     * @param string $expectedCallCount - optional (not applied to constructor method)
     * @return void
     */
    public function patchClassMethod($patchTarget, $patch=null, $expectedCallCount=null) {
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
            if($patch instanceof \Closure) {
                return call_user_func_array($patch, $args);
            }

            return $patch;
        });
    }
}
