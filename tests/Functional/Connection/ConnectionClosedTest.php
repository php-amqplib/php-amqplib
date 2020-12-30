<?php

namespace PhpAmqpLib\Tests\Functional\Connection;

use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Exception\AMQPChannelClosedException;
use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use PhpAmqpLib\Exception\AMQPHeartbeatMissedException;
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
     * @covers \PhpAmqpLib\Connection\AbstractConnection::waitFrame()
     * @covers \PhpAmqpLib\Wire\IO\StreamIO::read()
     * @covers \PhpAmqpLib\Wire\IO\SocketIO::read()
     *
     * @param string $type
     * @param bool $keepalive
     */
    public function mustThrowExceptionBrokenPipeWait($type, $keepalive)
    {
        $proxy = $this->createProxy();

        $options = array(
            'keepalive' => $keepalive,
        );
        /** @var AbstractConnection $connection */
        $connection = $this->conectionCreate(
            $type,
            $proxy->getHost(),
            $proxy->getPort(),
            $options
        );

        $channel = $connection->channel();
        $this->assertTrue($channel->isOpen());

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
     *           ["socket", 32768]
     * @covers \PhpAmqpLib\Wire\IO\StreamIO::write()
     * @covers \PhpAmqpLib\Wire\IO\SocketIO::write()
     *
     * @param string $type
     * @param int $size
     */
    public function mustThrowExceptionBrokenPipeWrite($type, $size)
    {
        $proxy = $this->createProxy();

        /** @var AbstractConnection $connection */
        $connection = $this->conectionCreate(
            $type,
            $proxy->getHost(),
            $proxy->getPort()
        );

        $channel = $connection->channel();
        $this->assertTrue($channel->isOpen());

        $this->queueBind($channel, $exchange_name = 'test_exchange_broken', $queue_name);
        $message = new AMQPMessage(
            str_repeat('0', $size),
            ['delivery_mode' => AMQPMessage::DELIVERY_MODE_NON_PERSISTENT]
        );

        $exception = null;
        // block proxy connection
        $proxy->close();
        unset($proxy);

        try {
            $channel->basicPublish($message, $exchange_name, $queue_name);
        } catch (\PHPUnit_Exception $exception) {
            throw $exception;
        } catch (\Exception $exception) {
        }

        $this->assertInstanceOf(AMQPConnectionClosedException::class, $exception);
        $this->assertEquals(SOCKET_EPIPE, $exception->getCode());
        $this->assertChannelClosed($channel);
        $this->assertConnectionClosed($connection);

        // 2nd publish call must return exception instantly cause connection is already closed
        $exception = null;
        try {
            $channel->basicPublish($message, $exchange_name, $queue_name);
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
    public function mustThrowExceptionMissedHeartbeat($type, $size)
    {
        $channel = $this->channelCreate($type, [
            'keepalive' => false,
            'heartbeat' => $heartbeat = 1,
            'timeout' => 3,
        ]);

        $this->queueBind($channel, $exchange_name = 'test_exchange_broken', $queue_name);
        $message = new AMQPMessage(
            str_repeat('0', $size),
            ['delivery_mode' => AMQPMessage::DELIVERY_MODE_NON_PERSISTENT]
        );

        // miss heartbeat
        sleep($heartbeat * 2 + 1);

        $exception = null;
        try {
            $channel->basicPublish($message, $exchange_name);
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
    public function mustIgnoreMissingHeartbeatAfterRecentWrite($type, $size)
    {
        $channel = $this->channelCreate($type, [
            'keepalive' => false,
            'heartbeat' => $heartbeat = 1,
            'timeout' => 3,
        ]);

        $this->queueBind($channel, $exchange_name = 'test_exchange_broken', $queue_name);
        $message = new AMQPMessage(
            str_repeat('0', $size),
            ['delivery_mode' => AMQPMessage::DELIVERY_MODE_NON_PERSISTENT]
        );

        $iteration = 0;
        do {
            sleep($heartbeat);
            $channel->basicPublish($message, $exchange_name, $queue_name);
        } while (++$iteration <= 3);

        $this->assertTrue($channel->isOpen());
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
    public function mustThrowExceptionAfterConnectionWasRestored($type)
    {
        $timeout = 1;
        $proxy = $this->createProxy();
        /** @var AbstractConnection $connection */
        $connection = $this->conectionCreate(
            $type,
            $proxy->getHost(),
            $proxy->getPort(),
            array('timeout' => $timeout)
        );

        $channel = $connection->channel();
        $this->assertTrue($channel->isOpen());

        $this->queueBind($channel, $exchange_name = 'test_exchange_broken', $queue_name);
        $message = new AMQPMessage(
            str_repeat('0', 1024 * 32), // 32kb fills up buffer completely on most OS
            ['delivery_mode' => AMQPMessage::DELIVERY_MODE_NON_PERSISTENT]
        );
        $channel->basicPublish($message, $exchange_name, $queue_name);

        // close proxy and wait longer than timeout
        unset($proxy);
        sleep($timeout);
        usleep(100000);
        $proxy = $this->createProxy();

        $exception = null;
        try {
            $channel->basicPublish($message, $exchange_name, $queue_name);
        } catch (\Exception $exception) {
        }

        unset($proxy);

        $this->assertInstanceOf(AMQPConnectionClosedException::class, $exception);
        $this->assertGreaterThan(0, $exception->getCode());
        $this->assertChannelClosed($channel);
        $this->assertConnectionClosed($connection);
    }
}
