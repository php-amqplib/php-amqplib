<?php

namespace PhpAmqpLib\Tests\Functional\Message;

use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Tests\Functional\Channel\ChannelTestCase;

/**
 * @group connection
 */
class AMQPMessageTest extends ChannelTestCase
{
    /**
     * @test
     */
    public function double_ack_throws_exception()
    {
        $sent = new AMQPMessage('test' . mt_rand());
        list($queue) = $this->channel->queue_declare();
        $this->channel->basic_publish($sent, '', $queue);

        $received = $this->channel->basic_get($queue);

        $this->assertSame($sent->getBody(), $received->getBody());
        $this->assertNotEmpty($received->getDeliveryTag());
        $this->assertSame($this->channel, $received->getChannel());
        $this->assertEquals('', $received->getExchange());
        $this->assertEquals($queue, $received->getRoutingKey());
        $this->assertEquals(0, $received->getMessageCount());
        $this->assertFalse($received->isRedelivered());
        $this->assertFalse($received->isTruncated());

        $received->ack();

        // 2nd ack must throw logic exception
        $this->expectException(\LogicException::class);
        $received->ack();
    }
}
