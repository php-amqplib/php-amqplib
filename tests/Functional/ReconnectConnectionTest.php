<?php

namespace PhpAmqpLib\Tests\Functional;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPLazyConnection;
use PhpAmqpLib\Connection\AMQPLazySocketConnection;
use PhpAmqpLib\Connection\AMQPSocketConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\TestCase;

/**
 * @group connection
 */
class ReconnectConnectionTest extends TestCase
{
    protected $connection = null;

    protected $channel = null;

    protected $exchange = 'reconnect_exchange';

    protected $queue = 'reconnect_queue';

    protected $msgBody = 'foo bar baz äëïöü';

    public function tearDown()
    {
        if ($this->channel) {
            $this->channel->exchangeDelete($this->exchange);
            $this->channel->queueDelete($this->queue);
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
     */
    public function lazyConnectionReconnect()
    {
        $this->connection = $this->getLazyConnection();

        $this->doTest();
    }

    /**
     * @test
     */
    public function lazyConnectionCloseReconnect()
    {
        $this->connection = $this->getLazyConnection();
        $this->setupChannel();
        $this->connection->close();
        $this->connection->reconnect();
        $this->setupChannel();

        $this->assertEquals($this->msgBody, $this->publishGet()->body);
    }

    /**
     * @test
     */
    public function socketConnectionReconnect()
    {
        $this->connection = $this->getSocketConnection();

        $this->doTest();
    }

    /**
     * @test
     */
    public function socketConnectionCloseReconnect()
    {
        $this->connection = $this->getSocketConnection();
        $this->connection->close();
        $this->connection->reconnect();
        $this->setupChannel();

        $this->assertEquals($this->msgBody, $this->publishGet()->body);
    }

    /**
     * @test
     */
    public function lazySocketConnectionCloseReconnect()
    {
        $this->connection = $this->getLazySocketConnection();
        $this->connection->close();
        $this->connection->reconnect();
        $this->setupChannel();

        $this->assertEquals($this->msgBody, $this->publishGet()->body);
    }

    /**
     * @test
     */
    public function lazyConnectionSocketReconnect()
    {
        $this->connection = $this->getLazySocketConnection();

        $this->doTest();
    }

    protected function getSocketConnection()
    {
        return new AMQPSocketConnection(HOST, PORT, USER, PASS, VHOST);
    }

    protected function getLazyConnection()
    {
        return new AMQPLazyConnection(HOST, PORT, USER, PASS, VHOST);
    }

    protected function getLazySocketConnection()
    {
        return new AMQPLazySocketConnection(HOST, PORT, USER, PASS, VHOST);
    }

    protected function doTest()
    {
        $this->setupChannel();
        $this->assertEquals($this->msgBody, $this->publishGet()->body);
        $this->connection->reconnect();
        $this->setupChannel();

        $this->assertEquals($this->msgBody, $this->publishGet()->body);
    }

    protected function setupChannel()
    {
        $this->channel = $this->connection->channel();
        $this->channel->exchangeDeclare($this->exchange, 'direct', false, false, false);
        $this->channel->queueDeclare($this->queue);
        $this->channel->queueBind($this->queue, $this->exchange, $this->queue);
    }

    protected function publishGet()
    {
        $msg = new AMQPMessage($this->msgBody, [
            'content_type' => 'text/plain',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_NON_PERSISTENT,
            'correlation_id' => 'my_correlation_id',
            'reply_to' => 'my_reply_to'
        ]);
        $this->channel->basicPublish($msg, $this->exchange, $this->queue);

        return $this->channel->basicGet($this->queue);
    }
}
