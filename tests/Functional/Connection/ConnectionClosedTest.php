<?php

namespace PhpAmqpLib\Tests\Functional\Connection;

use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Tests\Functional\AbstractConnectionTest;

/**
 * @group connection
 */
class ConnectionClosedTest extends AbstractConnectionTest
{
    /**
     * Try to wait for incoming data on blocked and closed connection.
     * @test
     * @small
     * @group connection
     * @group proxy
     * @testWith ["stream", false]
     *           ["stream", true]
     *           ["socket", false]
     *           ["socket", true]
     * @covers \PhpAmqpLib\Channel\AbstractChannel::wait()
     * @covers \PhpAmqpLib\Connection\AbstractConnection::wait_frame()
     * @covers \PhpAmqpLib\Wire\IO\StreamIO::read()
     * @covers \PhpAmqpLib\Wire\IO\SocketIO::read()
     *
     * @param string $type
     * @param bool $keepalive
     */
    public function must_throw_exception_broken_pipe_wait($type, $keepalive)
    {
        $proxy = $this->create_proxy();

        $options = array(
            'keepalive' => $keepalive,
        );
        /** @var AbstractConnection $connection */
        $connection = $this->conection_create(
            $type,
            $proxy->getHost(),
            $proxy->getPort(),
            $options
        );

        $channel = $connection->channel();
        $this->assertTrue($channel->is_open());

        $exception = null;
        // block and close connection after delay
        $proxy->mode('timeout', array('timeout' => 100));
        try {
            $channel->wait(null, false, 1);
        } catch (\Exception $exception) {
        }

        $this->assertInstanceOf('Exception', $exception);
        $this->assertInstanceOf('PhpAmqpLib\Exception\AMQPConnectionClosedException', $exception);
        $this->assertEquals(0, $exception->getCode());
        $this->assertChannelClosed($channel);
        $this->assertConnectionClosed($connection);
    }

    /**
     * Try to write(publish) to blocked(unresponsive) connection.
     * @test
     * @small
     * @group connection
     * @group proxy
     * @testWith ["stream"]
     *           ["socket"]
     * @covers \PhpAmqpLib\Wire\IO\StreamIO::write()
     * @covers \PhpAmqpLib\Wire\IO\SocketIO::write()
     *
     * @param string $type
     */
    public function must_throw_exception_broken_pipe_write($type)
    {
        $proxy = $this->create_proxy();

        /** @var AbstractConnection $connection */
        $connection = $this->conection_create(
            $type,
            $proxy->getHost(),
            $proxy->getPort()
        );

        $channel = $connection->channel();
        $this->assertTrue($channel->is_open());

        $this->queue_bind($channel, $exchange_name = 'test_exchange_broken', $queue_name);
        $message = new AMQPMessage(
            str_repeat('0', 1024 * 32), // 32kb fills up buffer completely on most OS
            ['delivery_mode' => AMQPMessage::DELIVERY_MODE_NON_PERSISTENT]
        );

        $exception = null;
        // block proxy connection
        $proxy->disable();

        try {
            $channel->basic_publish($message, $exchange_name, $queue_name);
        } catch (\Exception $exception) {
        }

        $this->assertInstanceOf('Exception', $exception);
        $this->assertInstanceOf('PhpAmqpLib\Exception\AMQPConnectionClosedException', $exception);
        $this->assertEquals(SOCKET_EPIPE, $exception->getCode());
        $this->assertChannelClosed($channel);
        $this->assertConnectionClosed($connection);
    }

    /**
     * Try to close and reopen connection after timeout.
     *
     * @test
     * @small
     * @group connection
     * @group proxy
     * @testWith ["stream"]
     *           ["socket"]
     * @covers \PhpAmqpLib\Wire\IO\StreamIO::write()
     * @covers \PhpAmqpLib\Wire\IO\SocketIO::write()
     * @param string $type
     */
    public function must_throw_exception_after_connection_was_restored($type)
    {
        $timeout = 1;
        $proxy = $this->create_proxy();
        /** @var AbstractConnection $connection */
        $connection = $this->conection_create(
            $type,
            $proxy->getHost(),
            $proxy->getPort(),
            array('timeout' => $timeout)
        );

        $channel = $connection->channel();
        $this->assertTrue($channel->is_open());

        $this->queue_bind($channel, $exchange_name = 'test_exchange_broken', $queue_name);
        $message = new AMQPMessage(
            str_repeat('0', 1024 * 32), // 32kb fills up buffer completely on most OS
            ['delivery_mode' => AMQPMessage::DELIVERY_MODE_NON_PERSISTENT]
        );
        $channel->basic_publish($message, $exchange_name, $queue_name);

        // close proxy and wait longer than timeout
        unset($proxy);
        sleep($timeout);
        usleep(100000);
        $proxy = $this->create_proxy();

        $exception = null;
        try {
            $channel->basic_publish($message, $exchange_name, $queue_name);
        } catch (\Exception $exception) {
        }

        $this->assertInstanceOf('Exception', $exception);
        $this->assertInstanceOf('PhpAmqpLib\Exception\AMQPConnectionClosedException', $exception);
        $this->assertGreaterThan(0, $exception->getCode());
        $this->assertChannelClosed($channel);
        $this->assertConnectionClosed($connection);
    }
}
