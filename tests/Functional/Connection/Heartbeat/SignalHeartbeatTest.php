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

    protected function setUp()
    {
        $this->connection = $this->conectionCreate(
            'stream',
            HOST,
            PORT,
            ['timeout' => 3, 'heartbeat' => $this->heartbeatTimeout]
        );
        $this->sender = new PCNTLHeartbeatSender($this->connection);
        $this->channel = $this->connection->channel();
        $this->channel->exchangeDeclare($this->exchangeName, 'direct', false, false, false);
        list($this->queueName, ,) = $this->channel->queueDeclare();
        $this->channel->queueBind($this->queueName, $this->exchangeName, $this->queueName);
    }

    public function tearDown()
    {
        if ($this->sender) {
            $this->sender->unregister();
        }
        if ($this->channel) {
            $this->channel->exchangeDelete($this->exchangeName);
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
    public function processMessageLongerThanHeartbeatTimeout()
    {
        $this->sender->register();

        $msg = new AMQPMessage($this->heartbeatTimeout, ['delivery_mode' => AMQPMessage::DELIVERY_MODE_NON_PERSISTENT]);

        $this->channel->basicPublish($msg, $this->exchangeName, $this->queueName);

        $this->channel->basicConsume(
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
        $delivery_info['channel']->basicAck($delivery_info['delivery_tag']);
        $delivery_info['channel']->basicCancel($delivery_info['consumer_tag']);

        self::assertEquals($this->heartbeatTimeout, (int)$msg->body);
    }
}
