<?php

namespace PhpAmqpLib\Tests\Functional;

use PhpAmqpLib\Connection\AMQPSocketConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Tests\Functional\Channel\ChannelTestCase;

/**
 * @group connection
 */
class TopicExchangeTest extends ChannelTestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->exchange->name = 'test_topic_exchange';
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPChannelClosedException
     */
    public function exchange_declare_with_closed_connection()
    {
        $this->connection->close();

        $this->channel->exchangeDeclare($this->exchange->name, 'topic');
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPChannelClosedException
     */
    public function exchange_declare_with_closed_channel()
    {
        $this->channel->close();

        $this->channel->exchangeDeclare($this->exchange->name, 'topic');
    }

    /**
     * @test
     */
    public function publish_with_confirm()
    {
        $this->channel->exchangeDeclare($this->exchange->name, 'topic');

        $deliveryTags = [];

        $this->channel->setAckHandler(function (AMQPMessage $message) use (&$deliveryTags) {
            $deliveryTags[] = (int) $message->get('delivery_tag');
            return false;
        });

        $this->channel->confirmSelect();

        $connection2 = new AMQPSocketConnection(HOST, PORT, USER, PASS, VHOST);
        $channel2 = $connection2->channel();

        $channel2->queueDeclare('tst.queue3');
        $channel2->queueBind('tst.queue3', $this->exchange->name, '#');

        $this->channel->basicPublish(new AMQPMessage('foo'), $this->exchange->name);
        $this->channel->basicPublish(new AMQPMessage('bar'), $this->exchange->name);

        $publishedMessagesProperty = new \ReflectionProperty(get_class($this->channel), 'publishedMessages');
        $publishedMessagesProperty->setAccessible(true);

        $this->channel->waitForPendingAcksReturns(1);

        $msg1 = $channel2->basicGet('tst.queue3');
        $msg2 = $channel2->basicGet('tst.queue3');

        $this->assertInstanceOf('PhpAmqpLib\Message\AMQPMessage', $msg1);
        $this->assertInstanceOf('PhpAmqpLib\Message\AMQPMessage', $msg2);
        $this->assertSame('foo', $msg1->getBody());
        $this->assertSame('bar', $msg2->getBody());
        $this->assertSame([1, 2], $deliveryTags);

        $channel2->close();
        $connection2->close();
    }
}
