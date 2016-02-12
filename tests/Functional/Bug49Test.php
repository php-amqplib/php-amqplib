<?php

namespace PhpAmqpLib\Tests\Functional;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Exception\AMQPProtocolException;

class Bug49Test extends \PHPUnit_Framework_TestCase
{
    /**
     * @var AMQPConnection
     */
    protected $connection;

    /**
     * @var AMQPChannel
     */
    protected $channel;

    /**
     * @var AMQPChannel
     */
    protected $channel2;

    public function setUp()
    {
        $this->connection = new AMQPConnection(HOST, PORT, USER, PASS, VHOST);
        $this->channel = $this->connection->channel();
        $this->channel2 = $this->connection->channel();
    }

    public function testDeclaration()
    {
        try {
            $this->channel->queue_declare('pretty.queue', true, true);
            $this->fail('Should have raised an exception');

        } catch (AMQPProtocolException $e) {
            if ($e->getCode() == 404) {
                $this->channel2->queue_declare('pretty.queue', false, true, true, true);
            } else {
                $this->fail('Should have raised a 404 Error');
            }
        }
    }

    public function tearDown()
    {
        if ($this->channel2) {
            $this->channel2->close();
        }

        if ($this->connection) {
            $this->connection->close();
        }
    }
}
