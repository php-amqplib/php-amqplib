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
    public function doubleAckThrowsException()
    {
        $sent = new AMQPMessage('test' . mt_rand());
        list($queue) = $this->channel->queueDeclare();
        $this->channel->basicPublish($sent, '', $queue);

        $received = $this->channel->basicGet($queue);

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
    public function publishConfirmMode()
    {
        $message = new AMQPMessage('test' . mt_rand());
        $confirmed = null;
        list($queue) = $this->channel->queueDeclare();
        $this->channel->setAckHandler(
            function (AMQPMessage $message) use (&$confirmed) {
                $confirmed = $message;
            }
        );

        $this->channel->confirmSelect();
        $this->channel->basicPublish($message, '', $queue);

        self::assertGreaterThan(0, $message->getDeliveryTag());
        self::assertEquals('', $message->getExchange());
        self::assertEquals($queue, $message->getRoutingKey());
        self::assertFalse($message->isRedelivered());

        $this->channel->waitForPendingAcks(3);

        self::assertSame($message, $confirmed);
    }
}
