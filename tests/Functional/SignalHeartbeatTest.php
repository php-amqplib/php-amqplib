<?php

namespace PhpAmqpLib\Tests\Functional;

use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Connection\Heartbeat\PCNTLHeartbeatSender;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\TestCase;

/**
 * @group signals
 */
class SignalHeartbeatTest extends TestCase
{
    /**
     * @var AbstractConnection
     */
    protected $connection;

    protected $exchangeName = 'test_pcntl_exchange';

    protected $queueName = null;

    protected $channel;

    protected $heartbeatTimeout = 4;

    protected function setUp()
    {
        if (!function_exists('pcntl_async_signals')) {
            $this->markTestSkipped('pcntl_async_signals is required');
        }

        $this->connection = AMQPStreamConnection::create_connection(
            [
                ['host' => HOST, 'port' => PORT, 'user' => USER, 'password' => PASS, 'vhost' => VHOST]
            ],
            ['heartbeat' => $this->heartbeatTimeout]
        );
        $this->channel = $this->connection->channel();
        $this->channel->exchange_declare($this->exchangeName, 'direct', false, false, false);
        list($this->queueName, ,) = $this->channel->queue_declare();
        $this->channel->queue_bind($this->queueName, $this->exchangeName, $this->queueName);
    }

    public function tearDown()
    {
        if ($this->channel) {
            $this->channel->exchange_delete($this->exchangeName);
            $this->channel->close();
            $this->channel = null;
        }
        if ($this->connection) {
            $this->connection->close();
            $this->connection = null;
        }
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
        $sender = new PCNTLHeartbeatSender($this->connection);
        $sender->register();

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
        $timeLeft = (int) $msg->body * 3;
        while ($timeLeft > 0) {
            $timeLeft = sleep($timeLeft);
        }

        $delivery_info = $msg->delivery_info;
        $delivery_info['channel']->basic_ack($delivery_info['delivery_tag']);
        $delivery_info['channel']->basic_cancel($delivery_info['consumer_tag']);

        $this->assertEquals($this->heartbeatTimeout, (int) $msg->body);
    }
}
