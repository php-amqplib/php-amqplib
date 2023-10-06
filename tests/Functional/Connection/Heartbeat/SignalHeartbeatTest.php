<?php

namespace PhpAmqpLib\Tests\Functional\Connection\Heartbeat;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\Heartbeat\PCNTLHeartbeatSender;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Tests\Functional\AbstractConnectionTest;

/**
 * @group connection
 * @group signals
 * @requires extension pcntl
 * @requires PHP 7.1
 */
class SignalHeartbeatTest extends AbstractConnectionTest
{
    /** @var AbstractConnection */
    protected $connection;

    /** @var string */
    protected $exchangeName = 'test_pcntl_exchange';

    /** @var string */
    protected $queueName;

    /** @var AMQPChannel */
    protected $channel;

    /** @var PCNTLHeartbeatSender */
    protected $sender;

    /** @var int */
    protected $heartbeatTimeout = 4;

    protected function setUpCompat()
    {
        $this->connection = $this->connection_create(
            'stream',
            HOST,
            PORT,
            ['timeout' => 3, 'heartbeat' => $this->heartbeatTimeout]
        );
        $this->sender = new PCNTLHeartbeatSender($this->connection);
        $this->channel = $this->connection->channel();
        $this->channel->exchange_declare($this->exchangeName, 'direct', false, false, false);
        list($this->queueName, ,) = $this->channel->queue_declare();
        $this->channel->queue_bind($this->queueName, $this->exchangeName, $this->queueName);
    }

    protected function tearDownCompat()
    {
        if ($this->sender) {
            $this->sender->unregister();
        }
        if ($this->channel) {
            $this->channel->exchange_delete($this->exchangeName);
            $this->channel->close();
        }
        if ($this->connection) {
            $this->connection->close();
        }
        $this->sender = null;
        $this->channel = null;
        $this->connection = null;
    }

    /**
     * @test
     *
     * @covers \PhpAmqpLib\Connection\Heartbeat\PCNTLHeartbeatSender::isSupported
     * @covers \PhpAmqpLib\Connection\Heartbeat\PCNTLHeartbeatSender::register
     * @covers \PhpAmqpLib\Connection\Heartbeat\PCNTLHeartbeatSender::registerListener
     * @covers \PhpAmqpLib\Connection\Heartbeat\PCNTLHeartbeatSender::unregister
     */
    public function process_message_longer_than_heartbeat_timeout()
    {
        $this->sender->register();

        $msg = new AMQPMessage($this->heartbeatTimeout, ['delivery_mode' => AMQPMessage::DELIVERY_MODE_NON_PERSISTENT]);

        $this->channel->basic_publish($msg, $this->exchangeName, $this->queueName);

        $this->channel->basic_consume(
            $this->queueName,
            '',
            false,
            false,
            false,
            false,
            [$this, 'processMessage']
        );

        while (count($this->channel->callbacks)) {
            $this->channel->wait();
        }
    }

    public function processMessage($msg)
    {
        $timeLeft = (int)$msg->body * 3;
        while ($timeLeft > 0) {
            $timeLeft = sleep($timeLeft);
        }

        $delivery_info = $msg->delivery_info;
        $delivery_info['channel']->basic_ack($delivery_info['delivery_tag']);
        $delivery_info['channel']->basic_cancel($delivery_info['consumer_tag']);

        self::assertEquals($this->heartbeatTimeout, (int)$msg->body);
    }
}
