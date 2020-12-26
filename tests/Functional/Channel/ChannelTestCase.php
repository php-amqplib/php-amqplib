<?php

namespace PhpAmqpLib\Tests\Functional\Channel;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPSocketConnection;
use PhpAmqpLib\Tests\TestCaseCompat;

/**
 * @group connection
 */
abstract class ChannelTestCase extends TestCaseCompat
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

    protected function setUpCompat()
    {
        $this->connection = new AMQPSocketConnection(HOST, PORT, USER, PASS, VHOST);

        $this->channel = $this->connection->channel();

        $this->exchange = (object) [
            'name' => '',
        ];

        $this->queue = (object) [
            'name' => null,
        ];

        $this->message = (object) [
            'body' => null,
            'properties' => null,
        ];
    }

    protected function tearDownCompat()
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
