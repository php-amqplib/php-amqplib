<?php

namespace PhpAmqpLib\Tests\Functional\Bug;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPProtocolChannelException;
use PhpAmqpLib\Exception\AMQPProtocolException;
use PHPUnit\Framework\TestCase;

/**
 * @group connection
 */
class Bug49Test extends TestCase
{
    protected $connection;

    protected $channel;

    protected $channel2;

    public function setUp()
    {
        $this->connection = new AMQPStreamConnection(HOST, PORT, USER, PASS, VHOST);
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
            $this->channel->queue_declare($queue = 'pretty.queue', true, true);
            $this->fail('Should have raised an exception');
        } catch (AMQPProtocolException $exception) {
            $this->assertInstanceOf(AMQPProtocolChannelException::class, $exception);
            $this->assertEquals(404, $exception->getCode());
        }
        $this->channel2->queue_declare($queue, false, true, true, true);
        $this->channel2->queue_delete($queue, false, false, true);
    }
}
