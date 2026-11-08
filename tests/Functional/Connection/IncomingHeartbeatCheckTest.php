<?php

namespace PhpAmqpLib\Tests\Functional\Connection;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\Heartbeat\PCNTLHeartbeatSender;
use PhpAmqpLib\Exception\AMQPHeartbeatMissedException;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Tests\Functional\AbstractConnectionTest;
use PhpAmqpLib\Tests\Functional\ToxiProxy;

/**
 * With the incoming heartbeat check on, something must have come in from the server within two
 * heartbeat intervals. The client's own writes (its heartbeats, publishes) don't count.
 *
 * @group connection
 * @see https://github.com/php-amqplib/php-amqplib/issues/1227
 */
class IncomingHeartbeatCheckTest extends AbstractConnectionTest
{
    private const HEARTBEAT = 1;

    /** @var ToxiProxy|null */
    private $proxy;

    protected function tearDownCompat()
    {
        if ($this->proxy) {
            $this->proxy->close();
            $this->proxy = null;
        }
    }

    /**
     * Nothing comes in anymore, while the client's writes still go through: a network that drops
     * traffic without closing the connection, or a frozen server behind a live TCP endpoint. A
     * consumer that polls without blocking keeps writing heartbeats, which kept the connection
     * looking healthy forever.
     *
     * @test
     * @group proxy
     * @testWith ["stream"]
     *           ["socket"]
     * @covers \PhpAmqpLib\Wire\IO\AbstractIO::checkBrokerHeartbeat()
     * @param string $type
     */
    public function must_throw_when_nothing_comes_in_while_client_keeps_writing($type)
    {
        list($connection, $channel) = $this->consumerWithIncomingDataDropped($type, true);
        $droppedSince = microtime(true);

        $exception = null;
        try {
            while (microtime(true) - $droppedSince < 5 * $this->limit()) {
                $channel->wait(null, true);
                usleep(100000);
            }
        } catch (AMQPHeartbeatMissedException $exception) {
        }

        $this->assertInstanceOf(AMQPHeartbeatMissedException::class, $exception);
        $this->assertLessThan(2 * $this->limit(), microtime(true) - $droppedSince);
        $this->assertFalse($connection->isConnected());
    }

    /**
     * The setup from #1227: a blocking wait() and a signal-based heartbeat sender.
     *
     * @test
     * @group proxy
     * @group signals
     * @requires extension pcntl
     * @testWith ["stream"]
     *           ["socket"]
     * @covers \PhpAmqpLib\Wire\IO\AbstractIO::checkBrokerHeartbeat()
     * @param string $type
     */
    public function must_throw_in_a_blocking_wait_with_a_heartbeat_sender($type)
    {
        list($connection, $channel) = $this->consumerWithIncomingDataDropped($type, true);
        $sender = new PCNTLHeartbeatSender($connection);
        $sender->register();
        $droppedSince = microtime(true);

        $exception = null;
        try {
            $channel->wait(null, false, 5 * $this->limit());
        } catch (AMQPHeartbeatMissedException $exception) {
        } catch (AMQPTimeoutException $exception) {
        } finally {
            $sender->unregister();
        }

        $this->assertInstanceOf(AMQPHeartbeatMissedException::class, $exception);
        $this->assertLessThan(2 * $this->limit(), microtime(true) - $droppedSince);
    }

    /**
     * Off by default: the connection behaves as before.
     *
     * @test
     * @group proxy
     * @covers \PhpAmqpLib\Wire\IO\AbstractIO::checkBrokerHeartbeat()
     */
    public function nothing_changes_while_the_check_is_off()
    {
        list($connection, $channel) = $this->consumerWithIncomingDataDropped('stream', false);

        $start = microtime(true);
        while (microtime(true) - $start < 2 * $this->limit()) {
            $channel->wait(null, true);
            usleep(100000);
        }

        $this->assertTrue($connection->isConnected());
    }

    /**
     * A client that only publishes never reads, so the server's heartbeats pile up unread on its
     * socket. They still count as incoming, and the connection must stay open.
     *
     * @test
     * @testWith ["stream"]
     *           ["socket"]
     * @covers \PhpAmqpLib\Wire\IO\AbstractIO::checkBrokerHeartbeat()
     * @param string $type
     */
    public function must_not_throw_for_a_publisher_that_never_reads($type)
    {
        $connection = $this->connection_create(
            $type,
            HOST,
            PORT,
            ['timeout' => 3, 'heartbeat' => self::HEARTBEAT, 'incoming_heartbeat_check' => true]
        );
        $channel = $connection->channel();
        $this->queue_bind($channel, $exchange_name = 'test_exchange_publish_only', $queue_name);
        $message = new AMQPMessage('test', ['delivery_mode' => AMQPMessage::DELIVERY_MODE_NON_PERSISTENT]);

        $start = microtime(true);
        while (microtime(true) - $start < 2 * $this->limit() + 1) {
            $channel->basic_publish($message, $exchange_name, $queue_name);
            usleep(250000);
        }

        $this->assertTrue($connection->isConnected());
        $channel->queue_delete($queue_name);
        $channel->exchange_delete($exchange_name);
        $channel->close();
        $connection->close();
    }

    /**
     * @param string $type
     * @param bool $check
     * @return array{AbstractConnection, AMQPChannel}
     */
    private function consumerWithIncomingDataDropped($type, $check)
    {
        $this->proxy = $this->create_proxy();
        $connection = $this->connection_create(
            $type,
            $this->proxy->getHost(),
            $this->proxy->getPort(),
            ['timeout' => 3, 'heartbeat' => self::HEARTBEAT, 'incoming_heartbeat_check' => $check]
        );
        $channel = $connection->channel();
        list($queue) = $channel->queue_declare('', false, false, true, true);
        $channel->basic_consume($queue, '', false, true, false, false, function () {
        });

        // Everything from the server is dropped; the client's writes still go through.
        $this->proxy->mode('timeout', ['timeout' => 0], 'downstream');

        return [$connection, $channel];
    }

    /**
     * Seconds without incoming data after which the heartbeat counts as missed.
     */
    private function limit(): int
    {
        return self::HEARTBEAT * 2 + 1;
    }
}
