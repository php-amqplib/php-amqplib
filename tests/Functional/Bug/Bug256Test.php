<?php

namespace PhpAmqpLib\Tests\Functional\Bug;

use PhpAmqpLib\Connection\AMQPSocketConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Tests\Functional\AbstractConnectionTest;
use PhpAmqpLib\Wire\AMQPTable;

/**
 * @group connection
 */
class Bug256Test extends AbstractConnectionTest
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
        $this->connection = $this->conectionCreate('socket');
        $this->channel = $this->connection->channel();

        $this->channel->exchangeDeclare($this->exchangeName, 'direct', false, true, false);

        $this->connection2 = $this->conectionCreate('stream');
        $this->channel2 = $this->connection->channel();

        list($this->queueName, ,) = $this->channel2->queueDeclare();
        $this->channel2->queueBind($this->queueName, $this->exchangeName, $this->queueName);
    }

    public function tearDown()
    {
        if ($this->channel) {
            $this->channel->exchangeDelete($this->exchangeName);
            $this->channel->close();
            $this->channel = null;
        }
        if ($this->connection) {
            $this->connection->close();
            $this->connection = null;
        }
        if ($this->channel2) {
            $this->channel2->close();
            $this->channel2 = null;
        }
        if ($this->connection2) {
            $this->connection2->close();
            $this->connection2 = null;
        }
    }

    /**
     * @test
     */
    public function frameOrder()
    {
        $msg = new AMQPMessage('');
        $hdrs = new AMQPTable(['x-foo' => 'bar']);
        $msg->set('application_headers', $hdrs);

        for ($i = 0; $i < $this->messageCount; $i++) {
            $this->channel->basicPublish($msg, $this->exchangeName, $this->queueName);
        }

        $this->channel2->basicConsume(
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
            $delivery_info['channel']->basicCancel($delivery_info['consumer_tag']);
        }
    }
}
