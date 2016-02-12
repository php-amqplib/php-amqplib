<?php

namespace PhpAmqpLib\Tests\Unit\Channel;

class AMQPChannelTest extends \PHPUnit_Framework_TestCase
{
    public function testCloseDoesNotEmitUndefinedPropertyWarningWhenSomeMethodsAreMocked()
    {
        $mockChannel = $this->getMockBuilder('\PhpAmqpLib\Channel\AMQPChannel')
            ->setMethods(array('queue_bind'))
            ->disableOriginalConstructor()
            ->getMock();

        /* @var $mockChannel \PhpAmqpLib\Channel\AMQPChannel */
        $mockChannel->close();
    }
}
