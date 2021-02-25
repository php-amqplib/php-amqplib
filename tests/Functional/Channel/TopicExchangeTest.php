<?php

namespace PhpAmqpLib\Tests\Functional\Channel;

use PhpAmqpLib\Connection\AMQPSocketConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Tests\Functional\Channel\ChannelTestCase;

/**
 * @group connection
 */
class TopicExchangeTest extends ChannelTestCase
{
    public function setUpCompat()
    {
        parent::setUpCompat();

        $this->exchange->name = 'test_topic_exchange';
    }

    /**
     * @test
     */
    public function exchange_declare_with_closed_connection()
    {
        $this->expectException(\PhpAmqpLib\Exception\AMQPChannelClosedException::class);

        $this->connection->close();

        $this->channel->exchange_declare($this->exchange->name, 'topic');
    }

    /**
     * @test
     */
    public function exchange_declare_with_closed_channel()
    {
        $this->expectException(\PhpAmqpLib\Exception\AMQPChannelClosedException::class);

        $this->channel->close();

        $this->channel->exchange_declare($this->exchange->name, 'topic');
    }

    /**
     * @test
     */
    public function publish_with_confirm()
    {
        $this->channel->exchange_declare($this->exchange->name, 'topic');

        $deliveryTags = [];

        $this->channel->set_ack_handler(function (AMQPMessage $message) use (&$deliveryTags) {
            $deliveryTags[] = (int) $message->get('delivery_tag');
            return false;
        });

        $this->channel->confirm_select();

        $connection2 = new AMQPSocketConnection(HOST, PORT, USER, PASS, VHOST);
        $channel2 = $connection2->channel();

        $channel2->queue_declare('tst.queue3');
        $channel2->queue_bind('tst.queue3', $this->exchange->name, '#');

        $this->channel->basic_publish(new AMQPMessage('foo'), $this->exchange->name);
        $this->channel->basic_publish(new AMQPMessage('bar'), $this->exchange->name);

        $publishedMessagesProperty = new \ReflectionProperty(get_class($this->channel), 'published_messages');
        $publishedMessagesProperty->setAccessible(true);

        $this->channel->wait_for_pending_acks_returns(1);

        $msg1 = $channel2->basic_get('tst.queue3');
        $msg2 = $channel2->basic_get('tst.queue3');

        $this->assertInstanceOf('PhpAmqpLib\Message\AMQPMessage', $msg1);
        $this->assertInstanceOf('PhpAmqpLib\Message\AMQPMessage', $msg2);
        $this->assertSame('foo', $msg1->getBody());
        $this->assertSame('bar', $msg2->getBody());
        $this->assertSame([1, 2], $deliveryTags);

        $channel2->close();
        $connection2->close();
    }
}
