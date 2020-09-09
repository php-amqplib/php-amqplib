<?php

namespace PhpAmqpLib\Tests\Functional\Message;

use LogicException;
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

        self::assertSame($sent->getBody(), $received->getBody());
        self::assertNotEmpty($received->getDeliveryTag());
        self::assertSame($this->channel, $received->getChannel());
        self::assertEquals('', $received->getExchange());
        self::assertEquals($queue, $received->getRoutingKey());
        self::assertEquals(0, $received->getMessageCount());
        self::assertFalse($received->isRedelivered());
        self::assertFalse($received->isTruncated());

        $received->ack();

        // 2nd ack must throw logic exception
        $this->expectException(LogicException::class);
        $received->ack();
    }

    /**
     * @test
     */
    public function publish_confirm_mode()
    {
        $message = new AMQPMessage('test' . mt_rand());
        $confirmed = null;
        list($queue) = $this->channel->queue_declare();
        $this->channel->set_ack_handler(
            function (AMQPMessage $message) use (&$confirmed) {
                $confirmed = $message;
            }
        );

        $this->channel->confirm_select();
        $this->channel->basic_publish($message, '', $queue);

        self::assertGreaterThan(0, $message->getDeliveryTag());
        self::assertEquals('', $message->getExchange());
        self::assertEquals($queue, $message->getRoutingKey());
        self::assertFalse($message->isRedelivered());

        $this->channel->wait_for_pending_acks(3);

        self::assertSame($message, $confirmed);
    }
}
