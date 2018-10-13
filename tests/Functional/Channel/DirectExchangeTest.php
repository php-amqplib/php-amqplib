<?php

namespace PhpAmqpLib\Tests\Functional;

use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Tests\Functional\Channel\ChannelTestCase;

class DirectExchangeTest extends ChannelTestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->exchange->name = 'test_direct_exchange';
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPChannelClosedException
     */
    public function exchange_declare_with_closed_connection()
    {
        $this->connection->close();

        $this->channel->exchange_declare($this->exchange->name, 'direct', false, true, false);
    }

    /**
     * @test
     * @expectedException \PhpAmqpLib\Exception\AMQPChannelClosedException
     */
    public function exchange_declare_with_closed_channel()
    {
        $this->channel->close();

        $this->channel->exchange_declare($this->exchange->name, 'direct', false, true, false);
    }

    /**
     * @test
     */
    public function basic_consume_foo()
    {
        $this->channel->exchange_declare($this->exchange->name, 'direct', false, false, false);
        list($this->queue->name, ,) = $this->channel->queue_declare();
        $this->channel->queue_bind($this->queue->name, $this->exchange->name, $this->queue->name);

        $this->message = (object) [
            'body' => 'foo',
            'properties' => [
                'content_type' => 'text/plain',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_NON_PERSISTENT,
                'correlation_id' => 'my_correlation_id',
                'reply_to' => 'my_reply_to',
            ],
        ];

        $msg = new AMQPMessage($this->message->body, $this->message->properties);

        $this->channel->basic_publish($msg, $this->exchange->name, $this->queue->name);

        $callback = function ($msg)
        {
            $this->assertEquals($this->message->body, $msg->body);
            $this->assertEquals(getmypid(), $msg->delivery_info['consumer_tag']);
            $this->assertEquals($this->queue->name, $msg->delivery_info['routing_key']);
            $this->assertEquals($this->exchange->name, $msg->delivery_info['exchange']);
            $this->assertEquals(false, $msg->delivery_info['redelivered']);
            $this->assertEquals($this->message->properties['content_type'], $msg->get('content_type'));
            $this->assertEquals($this->message->properties['correlation_id'], $msg->get('correlation_id'));
            $this->assertEquals($this->message->properties['reply_to'], $msg->get('reply_to'));
            $this->setExpectedException('OutOfBoundsException');
            $msg->get('no_property');
        };

        $this->channel->basic_consume(
            $this->queue->name,
            getmypid(),
            false,
            false,
            false,
            false,
            $callback
        );

        while (count($this->channel->callbacks)) {
            $this->channel->wait();
        }
    }
}
