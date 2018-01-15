<?php

namespace PhpAmqpLib\Tests\Functional;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPLazyConnection;
use PhpAmqpLib\Connection\AMQPLazySocketConnection;
use PhpAmqpLib\Connection\AMQPSocketConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\TestCase;

class ReconnectConnectionTest extends TestCase
{
    /**
     * Hold onto the connection
     * @var \PhpAmqpLib\Connection\AbstractConnection
     */
    protected $connection = null;

    /**
     * Hold onto the channel
     * @var \PhpAmqpLib\Channel\AbstractChannel|AMQPChannel
     */
    protected $channel = null;

    /**
     * Exchange name
     * @var string
     */
    protected $exchange = 'reconnect_exchange';

    /**
     * Queue name
     * @var string
     */
    protected $queue = 'reconnect_queue';

    /**
     * Queue message body
     * @var string
     */
    protected $msgBody = 'foo bar baz äëïöü';

    /**
     * Test the reconnect logic on the lazy connection, which is also stream connection
     */
    public function testLazyConnectionReconnect()
    {
        $this->connection = $this->getLazyConnection();
        $this->performTest();
    }

    /**
     * Get a new lazy connection
     * @return AMQPLazyConnection
     */
    protected function getLazyConnection()
    {
        return new AMQPLazyConnection(HOST, PORT, USER, PASS, VHOST);
    }

    /**
     * @return AMQPLazySocketConnection
     */
    protected function getLazySocketConnection()
    {
        return new AMQPLazySocketConnection(HOST, PORT, USER, PASS, VHOST);
    }

    /**
     * Perform the test after the connection has already been setup
     */
    protected function performTest()
    {
        // We need to a channel and exchange/queue
        $this->setupChannel();

        // Ensure normal publish then get works
        $this->assertEquals($this->msgBody, $this->publishGet()->body);

        // Reconnect the socket/stream connection
        $this->connection->reconnect();

        // Setup the channel and declarations again
        $this->setupChannel();

        // Ensure normal publish then get works (after reconnect attempt)
        $this->assertEquals($this->msgBody, $this->publishGet()->body);
    }

    /**
     * Setup the exchanges, and queues and channel
     */
    protected function setupChannel()
    {
        $this->channel = $this->connection->channel();

        $this->channel->exchange_declare($this->exchange, 'direct', false, false, false);
        $this->channel->queue_declare($this->queue);
        $this->channel->queue_bind($this->queue, $this->exchange, $this->queue);
    }

    /**
     * Publish a message, then get it immediately
     * @return \PhpAmqpLib\Message\AMQPMessage
     */
    protected function publishGet()
    {
        $msg = new AMQPMessage($this->msgBody, array(
            'content_type' => 'text/plain',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_NON_PERSISTENT,
            'correlation_id' => 'my_correlation_id',
            'reply_to' => 'my_reply_to'
        ));

        $this->channel->basic_publish($msg, $this->exchange, $this->queue);

        return $this->channel->basic_get($this->queue);
    }

    /**
     * Test the reconnect logic on the lazy connection, which is also stream connection after its been closed
     */
    public function testLazyConnectionCloseReconnect()
    {
        $this->connection = $this->getLazyConnection();

        // Force connection
        $this->setupChannel();

        // Manually close the connection
        $this->connection->close();

        // Attempt the reconnect after its been manually closed
        $this->connection->reconnect();
        $this->setupChannel();

        // Ensure normal publish then get works
        $this->assertEquals($this->msgBody, $this->publishGet()->body);
    }

    /**
     * Test the reconnect logic on the socket connection
     */
    public function testSocketConnectionReconnect()
    {
        $this->connection = $this->getSocketConnection();
        $this->performTest();
    }

    /**
     * Get a new socket connection
     * @return AMQPSocketConnection
     */
    protected function getSocketConnection()
    {
        return new AMQPSocketConnection(HOST, PORT, USER, PASS, VHOST);
    }

    /**
     * Test the reconnect logic on the socket connection after its been closed
     */
    public function testSocketConnectionCloseReconnect()
    {
        $this->connection = $this->getSocketConnection();
        $this->connection->close();

        // Attempt the reconnect after its been manually closed
        $this->connection->reconnect();
        $this->setupChannel();

        // Ensure normal publish then get works
        $this->assertEquals($this->msgBody, $this->publishGet()->body);
    }

    /**
     * Test the reconnect logic on the lazy connection, which is also socket connection after its been closed
     */
    public function testLazySocketConnectionCloseReconnect()
    {
        $this->connection = $this->getLazySocketConnection();
        $this->connection->close();

        // Attempt the reconnect after its been manually closed
        $this->connection->reconnect();
        $this->setupChannel();

        // Ensure normal publish then get works
        $this->assertEquals($this->msgBody, $this->publishGet()->body);
    }

    /**
     * Test the reconnect logic on the lazy connection, which is also socket connection
     */
    public function testLazyConnectionSocketReconnect()
    {
        $this->connection = $this->getLazySocketConnection();
        $this->performTest();
    }

    /**
     * Shut it down, delete exchanges, queues, close connections and channels
     */
    public function tearDown()
    {
        if ($this->channel) {
            $this->channel->exchange_delete($this->exchange);
            $this->channel->queue_delete($this->queue);
            $this->channel->close();
        }

        if ($this->connection) {
            $this->connection->close();
        }
    }
}
