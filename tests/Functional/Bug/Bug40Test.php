<?php

namespace PhpAmqpLib\Tests\Functional\Bug;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Tests\TestCaseCompat;

/**
 * @group connection
 */
class Bug40Test extends TestCaseCompat
{
    protected $exchangeName = 'test_exchange';

    protected $queueName1;

    protected $queueName2;

    protected $queue1Messages = 0;

    protected $connection;

    protected $channel;

    protected $channel2;

    protected function setUpCompat()
    {
        $this->connection = new AMQPStreamConnection(HOST, PORT, USER, PASS, VHOST);
        $this->channel = $this->connection->channel();
        $this->channel2 = $this->connection->channel();

        $this->channel->exchange_declare($this->exchangeName, 'direct', false, false, false);
        list($this->queueName1, ,) = $this->channel->queue_declare();
        list($this->queueName2, ,) = $this->channel->queue_declare();
        $this->channel->queue_bind($this->queueName1, $this->exchangeName, $this->queueName1);
        $this->channel->queue_bind($this->queueName2, $this->exchangeName, $this->queueName2);
    }

    protected function tearDownCompat()
    {
        if ($this->channel) {
            $this->channel->exchange_delete($this->exchangeName);
            $this->channel->close();
            $this->channel = null;
        }
        if ($this->channel2) {
            $this->channel2->close();
            $this->channel2 = null;
        }
        if ($this->connection) {
            $this->connection->close();
            $this->connection = null;
        }
    }

    /**
     * @test
     */
    public function frame_order()
    {
        $msg = new AMQPMessage('test message');
        $this->channel->basic_publish($msg, $this->exchangeName, $this->queueName1);
        $this->channel->basic_publish($msg, $this->exchangeName, $this->queueName1);
        $this->channel->basic_publish($msg, $this->exchangeName, $this->queueName2);

        $this->channel->basic_consume(
            $this->queueName1,
            '',
            false,
            true,
            false,
            false,
            [$this, 'processMessage1']
        );

        while ($this->channel->is_consuming()) {
            $this->channel->wait();
        }
    }

    public function processMessage1($msg)
    {
        $this->queue1Messages++;

        if ($this->queue1Messages === 1) {
            $this->channel2->basic_consume(
                $this->queueName2,
                '',
                false,
                true,
                false,
                false,
                [$this, 'processMessage2']
            );
        }

        while ($this->channel2->is_consuming()) {
            $this->channel2->wait();
        }

        if ($this->queue1Messages === 2) {
            $delivery_info = $msg->delivery_info;
            $delivery_info['channel']->basic_cancel($delivery_info['consumer_tag']);
        }
    }

    public function processMessage2($msg)
    {
        $delivery_info = $msg->delivery_info;
        $delivery_info['channel']->basic_cancel($delivery_info['consumer_tag']);
        $this->assertLessThan(2, $this->queue1Messages);
    }
}
