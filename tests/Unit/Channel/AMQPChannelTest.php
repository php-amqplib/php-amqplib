<?php

namespace PhpAmqpLib\Tests\Unit\Channel;

use PHPUnit\Framework\TestCase;

class AMQPChannelTest extends TestCase
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
