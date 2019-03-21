<?php

namespace PhpAmqpLib\Tests\Functional\Bug;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Exception\AMQPProtocolException;
use PHPUnit\Framework\TestCase;

class Bug49Test extends TestCase
{
    protected $connection;

    protected $channel;

    protected $channel2;

    public function setUp()
    {
        $this->connection = new AMQPConnection(HOST, PORT, USER, PASS, VHOST);
        $this->channel = $this->connection->channel();
        $this->channel2 = $this->connection->channel();
    }

    protected function tearDown()
    {
        if ($this->channel) {
            $this->channel->close();
            $this->channel = null;
        }
        if ($this->channel2) {
            $this->channel2->close();
            $this->channel2 = null;
        }
        if ($this->connection) {
            $this->connection->close();
            $this->connection = null;
        }
    }

    /**
     * @test
     */
    public function declaration()
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
}
