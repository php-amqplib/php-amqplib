<?php

namespace PhpAmqpLib\Tests\Functional;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPLazyConnection;
use PhpAmqpLib\Connection\AMQPSocketConnection;
use PhpAmqpLib\Message\AMQPMessage;

class ReconnectConnectionTest extends \PHPUnit_Framework_TestCase
{

    /**
     * Hold onto the connection
     * @var \PhpAmqpLib\Connection\AbstractConnection
     */
    protected $conn = null;

    /**
     * Hold onto the channel
     * @var \PhpAmqpLib\Channel\AbstractChannel|AMQPChannel
     */
    protected $ch = null;

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
     * Get a new lazy connection
     * @return AMQPLazyConnection
     */
    protected function getLazyConnection()
    {
        return new AMQPLazyConnection(HOST, PORT, USER, PASS, VHOST);
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
     * Test the reconnect logic on the lazy connection, which is also stream connection
     */
    public function testLazyConnectionReconnect()
    {
        $this->conn = $this->getLazyConnection();
        $this->performTest();
    }



    /**
     * Test the reconnect logic on the lazy connection, which is also stream connection after its been closed
     */
    public function testLazyConnectionCloseReconnect()
    {
        $this->conn = $this->getLazyConnection();

        // Force connection
        $this->setupChannel();

        // Manually close the connection
        $this->conn->close();

        // Attempt the reconnect after its been manually closed
        $this->conn->reconnect();
        $this->setupChannel();

        // Ensure normal publish then get works
        $this->assertEquals($this->msgBody, $this->publishGet()->body);
    }



    /**
     * Test the reconnect logic on the socket connection
     */
    public function testSocketConnectionReconnect()
    {
        $this->conn = $this->getSocketConnection();
        $this->performTest();
    }



    /**
     * Test the reconnect logic on the socket connection after its been closed
     */
    public function testSocketConnectionCloseReconnect()
    {
        $this->conn = $this->getSocketConnection();
        $this->conn->close();

        // Attempt the reconnect after its been manually closed
        $this->conn->reconnect();
        $this->setupChannel();

        // Ensure normal publish then get works
        $this->assertEquals($this->msgBody, $this->publishGet()->body);
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
        $this->conn->reconnect();

        // Setup the channel and declarations again
        $this->setupChannel();

        // Ensure normal publish then get works (after reconnect attempt)
        $this->assertEquals($this->msgBody, $this->publishGet()->body);
    }



    /**
     * Publish a message, then get it immediately
     * @return \PhpAmqpLib\Message\AMQPMessage
     */
    protected function publishGet()
    {
        $msg = new AMQPMessage($this->msgBody, array(
            'content_type' => 'text/plain',
            'delivery_mode' => 1,
            'correlation_id' => 'my_correlation_id',
            'reply_to' => 'my_reply_to'
        ));

        $this->ch->basic_publish($msg, $this->exchange, $this->queue);

        return $this->ch->basic_get($this->queue);
    }



    /**
     * Setup the exchanges, and queues and channel
     */
    protected function setupChannel()
    {
        $this->ch = $this->conn->channel();

        $this->ch->exchange_declare($this->exchange, 'direct', false, false, false);
        $this->ch->queue_declare($this->queue);
        $this->ch->queue_bind($this->queue, $this->exchange, $this->queue);
    }



    /**
     * Shut it down, delete exchanges, queues, close connections and channels
     */
    public function tearDown()
    {
        if ($this->ch) {
            $this->ch->exchange_delete($this->exchange);
            $this->ch->queue_delete($this->queue);
            $this->ch->close();
        }

        if ($this->conn) {
            $this->conn->close();
        }
    }
}
