<?php

namespace PhpAmqpLib\Tests\Unit\Channel;

use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Tests\Unit\Test\BufferIO;
use PhpAmqpLib\Tests\Unit\Test\TestChannel;
use PhpAmqpLib\Tests\Unit\Test\TestConnection;
use PHPUnit\Framework\TestCase;

class AMQPChannelTest extends TestCase
{
    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPConnectionBlockedException
     */
    public function blocked_connection_exception_on_publish()
    {
        $connection = new TestConnection('user', 'pass', '/', false, 'PLAIN', null, '', new BufferIO());
        $connection->setIsBlocked(true);
        $channel = new TestChannel($connection, 1);
        $channel->basic_publish(new AMQPMessage());
    }
}
