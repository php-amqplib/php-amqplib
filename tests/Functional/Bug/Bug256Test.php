<?php

namespace PhpAmqpLib\Tests\Functional\Bug;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Connection\AMQPSocketConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use PHPUnit\Framework\TestCase;

class Bug256Test extends TestCase
{
    protected $exchangeName = 'test_exchange';

    protected $queueName = null;

    protected $messageCount = 100;

    protected $consumedCount = 0;

    protected $connection;

    protected $connection2;

    protected $channel;

    protected $channel2;

    public function setUp()
    {
        $this->connection = new AMQPSocketConnection(HOST, PORT, USER, PASS, VHOST);
        $this->channel = $this->connection->channel();

        $this->channel->exchange_declare($this->exchangeName, 'direct', false, true, false);

        $this->connection2 = new AMQPStreamConnection(HOST, PORT, USER, PASS, VHOST);
        $this->channel2 = $this->connection->channel();

        list($this->queueName, ,) = $this->channel2->queue_declare();
        $this->channel2->queue_bind($this->queueName, $this->exchangeName, $this->queueName);
    }

    public function tearDown()
    {
        if ($this->channel) {
            $this->channel->exchange_delete($this->exchangeName);
            $this->channel->close();
        }
        if ($this->connection) {
            $this->connection->close();
        }
        if ($this->channel2) {
            $this->channel2->close();
        }
        if ($this->connection2) {
            $this->connection2->close();
        }
    }

    /**
     * @test
     */
    public function frame_order()
    {
        $msg = new AMQPMessage('');
        $hdrs = new AMQPTable(['x-foo' => 'bar']);
        $msg->set('application_headers', $hdrs);

        for ($i = 0; $i < $this->messageCount; $i++) {
            $this->channel->basic_publish($msg, $this->exchangeName, $this->queueName);
        }

        $this->channel2->basic_consume(
            $this->queueName,
            '',
            false,
            true,
            false,
            false,
            [$this, 'processMessage']
        );

        while (count($this->channel2->callbacks)) {
            $this->channel2->wait();
        }
    }

    public function processMessage(AMQPMessage $message)
    {
        $this->consumedCount++;

        $this->assertEquals(['x-foo' => 'bar'], $message->get('application_headers')->getNativeData());

        if ($this->consumedCount >= $this->messageCount) {
            $delivery_info = $message->delivery_info;
            $delivery_info['channel']->basic_cancel($delivery_info['consumer_tag']);
        }
    }
}
