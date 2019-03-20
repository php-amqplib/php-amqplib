<?php

namespace PhpAmqpLib\Tests\Functional\Channel;

use PhpAmqpLib\Connection\AMQPSocketConnection;
use PHPUnit\Framework\TestCase;

abstract class ChannelTestCase extends TestCase
{
    protected $connection;

    protected $channel;

    protected $exchange;

    protected $queue;

    protected $message;

    public function setUp()
    {
        $this->connection = new AMQPSocketConnection(HOST, PORT, USER, PASS, VHOST);

        $this->channel = $this->connection->channel();

        $this->exchange = (object) [
            'name' => null,
        ];

        $this->queue = (object) [
            'name' => null,
        ];

        $this->message = (object) [
            'body' => null,
            'properties' => null,
        ];
    }

    public function tearDown()
    {
        $this->channel->close();
        $this->channel = null;
        $this->connection->close();
        $this->connection = null;
    }
}
