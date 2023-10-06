<?php

namespace PhpAmqpLib\Tests\Functional\Connection;

use PhpAmqpLib\Exception;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Tests\Functional\AbstractConnectionTest;

/**
 * @group connection
 */
class ConnectionUnresponsiveTest extends AbstractConnectionTest
{
    /**
     * Use mocked write functions to simulate completely blocked connections.
     * @test
     * @small
     * @testWith ["stream"]
     *           ["socket"]
     * @covers \PhpAmqpLib\Wire\IO\StreamIO::write()
     * @covers \PhpAmqpLib\Wire\IO\SocketIO::write()
     * @param string $type
     */
    public function must_throw_exception_on_completely_blocked_connection($type)
    {
        self::$blocked = false;
        $connection = $this->connection_create($type);
        $channel = $connection->channel();
        $this->assertTrue($channel->is_open());
        $this->queue_bind($channel, $exchange_name = 'test_exchange_broken', $queue_name);

        self::$blocked = true;
        $message = new AMQPMessage(
            str_repeat('0', 8),
            ['delivery_mode' => AMQPMessage::DELIVERY_MODE_NON_PERSISTENT]
        );

        $exception = null;
        try {
            $channel->basic_publish($message, $exchange_name, $queue_name);
        } catch (\Exception $exception) {
        }

        self::$blocked = false;

        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertInstanceOf(Exception\AMQPTimeoutException::class, $exception);
        $this->assertEquals(1, $exception->getTimeout());

        $this->assertTrue($channel->is_open());
        $this->assertTrue($connection->isConnected());
    }

    /**
     * @test
     * @testWith ["stream"]
     * @group proxy
     * @covers \PhpAmqpLib\Connection\AbstractConnection::connect()
     * @param string $type
     */
    public function must_throw_timeout_exception_on_missing_connect_response($type)
    {
        $proxy = $this->create_proxy();
        $proxy->mode('timeout', ['timeout' => 0], 'downstream');
        $connection = null;
        $exception = null;
        try {
            $connection = $this->connection_create(
                $type,
                $proxy->getHost(),
                $proxy->getPort(),
                array('timeout' => 3, 'connectionTimeout' => .1, 'heartbeat' => 1)
            );
        } catch (\Exception $exception) {
        }

        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertInstanceOf(Exception\AMQPTimeoutException::class, $exception);
        $this->assertNull($connection);
    }
}
