<?php

namespace PhpAmqpLib\Tests\Functional\Channel;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPSocketConnection;
use PHPUnit\Framework\TestCase;

/**
 * @group connection
 */
abstract class ChannelTestCase extends TestCase
{
    /** @var AbstractConnection */
    protected $connection;

    /** @var AMQPChannel */
    protected $channel;

    /** @var object */
    protected $exchange;

    /** @var object */
    protected $queue;

    /** @var object */
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
        if ($this->channel) {
            $this->channel->close();
            $this->channel = null;
        }
        if ($this->connection) {
            $this->connection->close();
            $this->connection = null;
        }
    }
}
