<?php

namespace PhpAmqpLib\Tests\Functional\Connection;

use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Exception\AMQPChannelClosedException;
use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use PhpAmqpLib\Exception\AMQPHeartbeatMissedException;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Tests\Functional\AbstractConnectionTest;
use PhpAmqpLib\Tests\Functional\ToxiProxy;

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
        $connection = $this->connection_create(
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

        $this->assertInstanceOf(AMQPConnectionClosedException::class, $exception);
        $this->assertEquals(0, $exception->getCode());
        $this->assertChannelClosed($channel);
        $this->assertConnectionClosed($connection);
    }

    /**
     * Try to write(publish) to blocked(unresponsive) or closed connection.
     * Must throw correct exception for small and big data frames.
     * @test
     * @small
     * @group connection
     * @group proxy
     * @testWith ["stream", 1024]
     *           ["stream", 32768]
     *           ["socket", 102400]
     * @covers \PhpAmqpLib\Wire\IO\StreamIO::write()
     * @covers \PhpAmqpLib\Wire\IO\SocketIO::write()
     *
     * @param string $type
     * @param int $size
     */
    public function must_throw_exception_broken_pipe_write($type, $size)
    {
        $proxy = $this->create_proxy();

        $connection = $this->connection_create(
            $type,
            $proxy->getHost(),
            $proxy->getPort()
        );



        $channel = $connection->channel();
        $this->assertTrue($channel->is_open());

        $this->queue_bind($channel, $exchange_name = 'test_exchange_broken', $queue_name);
        $message = new AMQPMessage(
            str_repeat('0', $size),
            ['delivery_mode' => AMQPMessage::DELIVERY_MODE_NON_PERSISTENT]
        );

        $exception = null;
        // drop proxy connection
        $proxy->close();
        unset($proxy);

        // send data frames until buffer gets full
        $retry = 0;
        do {
            try {
                $channel->basic_publish($message, $exchange_name, $queue_name);
            } catch (\PHPUnit_Exception $exception) {
                throw $exception;
            } catch (\Exception $exception) {
                break;
            }
        } while (!$exception && ++$retry < 100);

        $this->assertInstanceOf(AMQPConnectionClosedException::class, $exception);
        $this->assertEquals(SOCKET_EPIPE, $exception->getCode());
        $this->assertChannelClosed($channel);
        $this->assertConnectionClosed($connection);

        // 2nd publish call must return exception instantly cause connection is already closed
        $exception = null;
        try {
            $channel->basic_publish($message, $exchange_name, $queue_name);
        } catch (\Exception $exception) {
        }
        $this->assertInstanceOf(AMQPChannelClosedException::class, $exception);
    }

    /**
     * Try to write(publish) to closed connection after missed heartbeat.
     * @test
     * @medium
     * @group connection
     * @testWith ["stream", 1024]
     *           ["stream", 32768]
     *           ["socket", 1024]
     *           ["socket", 32768]
     * @covers \PhpAmqpLib\Wire\IO\StreamIO::write()
     * @covers \PhpAmqpLib\Wire\IO\SocketIO::write()
     *
     * @param string $type
     * @param int $size
     */
    public function must_throw_exception_missed_heartbeat($type, $size)
    {
        $channel = $this->channel_create($type, [
            'keepalive' => false,
            'heartbeat' => $heartbeat = 1,
            'timeout' => 3,
        ]);

        $this->queue_bind($channel, $exchange_name = 'test_exchange_broken', $queue_name);
        $message = new AMQPMessage(
            str_repeat('0', $size),
            ['delivery_mode' => AMQPMessage::DELIVERY_MODE_NON_PERSISTENT]
        );

        // miss heartbeat
        sleep($heartbeat * 2 + 1);

        $exception = null;
        try {
            $channel->basic_publish($message, $exchange_name);
        } catch (\PHPUnit_Exception $exception) {
            throw $exception;
        } catch (\Exception $exception) {
        }

        $this->assertInstanceOf(AMQPHeartbeatMissedException::class, $exception);
        $this->assertChannelClosed($channel);
    }

    /**
     * When client constantly publish messages in async manner and broker does not send heartbeats.
     * @test
     * @medium
     * @group connection
     * @testWith ["stream", 1024]
     *           ["stream", 32768]
     *           ["socket", 1024]
     *           ["socket", 32768]
     * @covers \PhpAmqpLib\Wire\IO\StreamIO::write()
     * @covers \PhpAmqpLib\Wire\IO\SocketIO::write()
     *
     * @param string $type
     * @param int $size
     */
    public function must_ignore_missing_heartbeat_after_recent_write($type, $size)
    {
        $channel = $this->channel_create($type, [
            'keepalive' => false,
            'heartbeat' => $heartbeat = 1,
            'timeout' => 3,
        ]);

        $this->queue_bind($channel, $exchange_name = 'test_exchange_broken', $queue_name);
        $message = new AMQPMessage(
            str_repeat('0', $size),
            ['delivery_mode' => AMQPMessage::DELIVERY_MODE_NON_PERSISTENT]
        );

        $iteration = 0;
        do {
            sleep($heartbeat);
            $channel->basic_publish($message, $exchange_name, $queue_name);
        } while (++$iteration <= 3);

        $this->assertTrue($channel->is_open());
        // this performs write and read with additional heartbeat check
        $channel->close();
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
        $connection = $this->connection_create(
            $type,
            $proxy->getHost(),
            $proxy->getPort(),
            array('timeout' => $timeout)
        );

        $channel = $connection->channel();
        $this->assertTrue($channel->is_open());

        $this->queue_bind($channel, $exchange_name = 'test_exchange_broken', $queue_name);
        $message = new AMQPMessage(
            str_repeat('0', 1024 * 100), // 32kb fills up buffer completely on most OS
            ['delivery_mode' => AMQPMessage::DELIVERY_MODE_NON_PERSISTENT]
        );
        $channel->basic_publish($message, $exchange_name, $queue_name);

        // drop proxy connection and wait longer than timeout
        $proxy->close();
        sleep($timeout);
        usleep(100000);
        $proxy = $this->create_proxy();

        $exception = null;
        // send data frames until buffer gets full
        $retry = 0;
        do {
            try {
                $channel->basic_publish($message, $exchange_name, $queue_name);
            } catch (\PHPUnit_Exception $exception) {
                throw $exception;
            } catch (\Exception $exception) {
                break;
            }
        } while (!$exception && ++$retry < 100);

        $proxy->close();
        unset($proxy);

        $this->assertInstanceOf(AMQPConnectionClosedException::class, $exception);
        $this->assertGreaterThan(0, $exception->getCode());
        $this->assertChannelClosed($channel);
        $this->assertConnectionClosed($connection);
    }

    /**
     * Try to close and reopen connection with two channels.
     *
     * @test
     * @small
     * @group connection
     * @group proxy
     * @testWith ["stream"]
     *            ["socket"]
     * @covers \PhpAmqpLib\Wire\IO\StreamIO::write()
     * @covers \PhpAmqpLib\Wire\IO\SocketIO::write()
     *
     * @param string $type
     */
    public function must_throw_exception_after_connection_was_restored_with_two_channels($type)
    {
        $timeout = 1;

        // Create proxy part
        $host = trim(getenv('TOXIPROXY_AMQP_TARGET'));
        if (empty($host)) {
            $host = HOST;
        }
        $proxy = new ToxiProxy('amqp_connection', $this->get_toxiproxy_host());
        $proxy->open($host, PORT, $this->get_toxiproxy_amqp_port());

        /** @var AbstractConnection $connection */
        $connection = $this->connection_create(
            $type,
            $proxy->getHost(),
            $proxy->getPort(),
            array('timeout' => $timeout)
        );

        $channel = $connection->channel();
        $anotherChannel = $connection->channel();

        $this->assertTrue($channel->is_open());
        $this->assertTrue($anotherChannel->is_open());

        $this->queue_bind($channel, $exchange_name = 'test_exchange_broken', $queue_name);
        $message = new AMQPMessage(
            'test',
            ['delivery_mode' => AMQPMessage::DELIVERY_MODE_NON_PERSISTENT]
        );
        $channel->basic_publish($message, $exchange_name, $queue_name);

        // drop proxy connection and wait longer than timeout
        $proxy->close();
        sleep($timeout);
        usleep(100000);
        // Reopen proxy
        $proxy->open($host, PORT, $this->get_toxiproxy_amqp_port());

        $retry = 0;
        $exception = null;
        do {
            try {
                $channel->basic_publish($message, $exchange_name, $queue_name);
            } catch (\PHPUnit_Exception $exception) {
                throw $exception;
            } catch (\Exception $exception) {
                break;
            }
        } while (!$exception && ++$retry < 100);

        $this->assertInstanceOf(AMQPConnectionClosedException::class, $exception);
        $this->assertGreaterThan(0, $exception->getCode());
        $this->assertChannelClosed($channel);

        // Now lets reconnect
        $connection->reconnect();

        // Both old channels must be closed
        $this->assertChannelClosed($channel);
        $this->assertChannelClosed($anotherChannel);
    }
}
