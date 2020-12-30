<?php

namespace PhpAmqpLib\Tests\Functional\Bug;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\TestCase;

/**
 * @group connection
 */
class Bug40Test extends TestCase
{
    protected $exchangeName = 'test_exchange';

    protected $queueName1;

    protected $queueName2;

    protected $queue1Messages = 0;

    protected $connection;

    protected $channel;

    protected $channel2;

    public function setUp()
    {
        $this->connection = new AMQPStreamConnection(HOST, PORT, USER, PASS, VHOST);
        $this->channel = $this->connection->channel();
        $this->channel2 = $this->connection->channel();

        $this->channel->exchangeDeclare($this->exchangeName, 'direct', false, false, false);
        list($this->queueName1, ,) = $this->channel->queueDeclare();
        list($this->queueName2, ,) = $this->channel->queueDeclare();
        $this->channel->queueBind($this->queueName1, $this->exchangeName, $this->queueName1);
        $this->channel->queueBind($this->queueName2, $this->exchangeName, $this->queueName2);
    }

    public function tearDown()
    {
        if ($this->channel) {
            $this->channel->exchangeDelete($this->exchangeName);
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
    public function frameOrder()
    {
        $msg = new AMQPMessage('test message');
        $this->channel->basicPublish($msg, $this->exchangeName, $this->queueName1);
        $this->channel->basicPublish($msg, $this->exchangeName, $this->queueName1);
        $this->channel->basicPublish($msg, $this->exchangeName, $this->queueName2);

        $this->channel->basicConsume(
            $this->queueName1,
            '',
            false,
            true,
            false,
            false,
            [$this, 'processMessage1']
        );

        while ($this->channel->isConsuming()) {
            $this->channel->wait();
        }
    }

    public function processMessage1($msg)
    {
        $this->queue1Messages++;

        if ($this->queue1Messages === 1) {
            $this->channel2->basicConsume(
                $this->queueName2,
                '',
                false,
                true,
                false,
                false,
                [$this, 'processMessage2']
            );
        }

        while ($this->channel2->isConsuming()) {
            $this->channel2->wait();
        }

        if ($this->queue1Messages === 2) {
            $delivery_info = $msg->delivery_info;
            $delivery_info['channel']->basicCancel($delivery_info['consumer_tag']);
        }
    }

    public function processMessage2($msg)
    {
        $delivery_info = $msg->delivery_info;
        $delivery_info['channel']->basicCancel($delivery_info['consumer_tag']);
        $this->assertLessThan(2, $this->queue1Messages);
    }
}
