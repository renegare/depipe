<?php

namespace App\Test;

class AbstractTaskTest extends \PHPUnit_Framework_TestCase {

    public function testRun() {
        $mockTask = $this->getMockBuilder('App\AbstractTask')
            ->disableOriginalConstructor()
            ->setMethods(['doRun'])
            ->getMock();

        $mockTask->expects($this->once())
            ->method('doRun');

        $mockTask->run();
    }

    /**
     * @expectedException Exception
     */
    public function testLogError() {
        $expectedException = new \Exception('mock-error-message');

        $mockTask = $this->getMockBuilder('App\AbstractTask')
            ->disableOriginalConstructor()
            ->setMethods(['doRun'])
            ->getMock();

        $mockLogger = $this->getMockForAbstractClass('Psr\Log\LoggerInterface');
        $mockLogger->expects($this->any())
            ->method('log')
            ->will($this->returnCallback(function($level, $message, $context) use ($expectedException, $mockTask) {
                if($level === 'error') {
                    $this->assertEquals(sprintf('ERROR RUNNING TASK %s: mock-error-message', get_class($mockTask)), $message);
                    $this->assertEquals(['exception' => $expectedException], $context);
                }
            }));

        $mockTask->expects($this->once())
            ->method('doRun')
            ->will($this->returnCallback(function() use ($expectedException){
                throw $expectedException;
            }));
        $mockTask->setLogger($mockLogger);
        $mockTask->run();
    }

    public function testMagicSetMethodFluentApi() {

        $mockLogger = $this->getMockForAbstractClass('Psr\Log\LoggerInterface');
        $mockLogger->expects($this->once())
            ->method('log')
            ->will($this->returnCallback(function($level, $message) {
                $this->assertEquals('Test Logger', $message);
            }));

        $mockTask = $this->getMockBuilder('App\AbstractTask')
            ->disableOriginalConstructor()
            ->setMethods(['doRun'])
            ->getMock();

        $this->assertSame($mockTask, $mockTask->setLogger($mockLogger));

        $mockTask->info('Test Logger');
    }

    /**
     * @expectedException OutOfBoundsException
     */
    public function testMagicSetMethodForNonExistentParam() {
        $abstractTask = $this->getAbstractTaskInstance();
        $abstractTask->setNonExistantParam('Should not work');
    }

    /**
     * @expectedException BadMethodCallException
     */
    public function testOnlySetMethodsAreActioned() {
        $abstractTask = $this->getAbstractTaskInstance();
        $abstractTask->doSomethingAmazing('Should not work');
    }

    protected function getAbstractTaskInstance() {
        return $this->getMockBuilder('App\AbstractTask')
            ->disableOriginalConstructor()
            ->setMethods(['doRun'])
            ->getMock();
    }
}
