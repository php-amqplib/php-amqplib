<?php

namespace PhpAmqpLib\Tests\Unit\Wire\IO;

use PhpAmqpLib\Exception\AMQPHeartbeatMissedException;
use PhpAmqpLib\Wire\IO\AbstractIO;
use PhpAmqpLib\Wire\IO\SocketIO;
use PhpAmqpLib\Wire\IO\StreamIO;
use PHPUnit\Framework\TestCase;

/**
 * @group connection
 * @covers \PhpAmqpLib\Wire\IO\AbstractIO::checkBrokerHeartbeat()
 */
class AbstractIOHeartbeatTest extends TestCase
{
    /**
     * @test
     */
    public function no_incoming_data_while_client_writes_is_a_missed_heartbeat()
    {
        $io = $this->io(10, 0, false);

        $this->expectException(AMQPHeartbeatMissedException::class);
        $io->checkBroker();
    }

    /**
     * @test
     */
    public function unread_data_from_the_server_keeps_the_connection()
    {
        $io = $this->io(10, 0, true);

        $io->checkBroker();
        $this->assertSame(1, $io->unreadChecks);

        // Seen once, it counts as activity for the next two intervals: no check on every write.
        $io->checkBroker();
        $this->assertSame(1, $io->unreadChecks);
    }

    /**
     * @test
     */
    public function recent_read_needs_no_socket_check()
    {
        $io = $this->io(0.5, 0, false);

        $io->checkBroker();
        $this->assertSame(0, $io->unreadChecks);
    }

    /**
     * @test
     */
    public function no_traffic_either_way_is_a_missed_heartbeat_without_a_socket_check()
    {
        $io = $this->io(10, 10, true);

        try {
            $io->checkBroker();
            $this->fail('Expected AMQPHeartbeatMissedException');
        } catch (AMQPHeartbeatMissedException $exception) {
        }
        $this->assertSame(0, $io->unreadChecks);
    }

    /**
     * @test
     */
    public function disabled_heartbeat_checks_nothing()
    {
        $io = $this->io(10, 0, false, 0);

        $io->checkBroker();
        $this->assertSame(0, $io->unreadChecks);
    }

    /**
     * @test
     */
    public function off_by_default()
    {
        $io = $this->io(10, 0, false, 1, false);

        $io->checkBroker();
        $this->assertSame(0, $io->unreadChecks);
    }

    /**
     * @test
     * @covers \PhpAmqpLib\Wire\IO\StreamIO::hasUnreadData()
     */
    public function stream_io_sees_unread_data_without_consuming_it()
    {
        $this->skipOnWindows();
        list($local, $remote) = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $io = new StreamIO('localhost', 5672, 1, 1);
        $this->setSocket($io, StreamIO::class, $local);

        $this->assertFalse($this->hasUnreadData($io));
        fwrite($remote, self::HEARTBEAT_FRAME);
        $this->assertTrue($this->hasUnreadData($io));
        $this->assertSame(self::HEARTBEAT_FRAME, fread($local, 8));
        $this->assertFalse($this->hasUnreadData($io));
    }

    /**
     * @test
     * @covers \PhpAmqpLib\Wire\IO\SocketIO::hasUnreadData()
     */
    public function socket_io_sees_unread_data_without_consuming_it()
    {
        $this->skipOnWindows();
        $pair = array();
        $this->assertTrue(socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $pair));
        list($local, $remote) = $pair;
        $io = new SocketIO('localhost', 5672);
        $this->setSocket($io, SocketIO::class, $local);

        $this->assertFalse($this->hasUnreadData($io));
        socket_write($remote, self::HEARTBEAT_FRAME);
        $this->assertTrue($this->hasUnreadData($io));
        $this->assertSame(self::HEARTBEAT_FRAME, socket_read($local, 8));
        $this->assertFalse($this->hasUnreadData($io));
    }

    private const HEARTBEAT_FRAME = "\x08\x00\x00\x00\x00\x00\x00\xCE";

    private function skipOnWindows(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Unix socket pairs are not available on Windows');
        }
    }

    /**
     * @param AbstractIO $io
     * @param string $class
     * @param mixed $socket
     */
    private function setSocket($io, $class, $socket): void
    {
        $property = new \ReflectionProperty($class, 'sock');
        $property->setAccessible(true);
        $property->setValue($io, $socket);
    }

    /**
     * @param AbstractIO $io
     */
    private function hasUnreadData($io): bool
    {
        $method = new \ReflectionMethod($io, 'hasUnreadData');
        $method->setAccessible(true);

        return $method->invoke($io);
    }

    /**
     * @param float $secondsSinceRead
     * @param float $secondsSinceWrite
     * @param bool $unreadData
     * @param int $heartbeat
     * @param bool $check
     * @return AbstractIO
     */
    private function io($secondsSinceRead, $secondsSinceWrite, $unreadData, $heartbeat = 1, $check = true)
    {
        $io = new class ($unreadData) extends AbstractIO {
            /** @var bool */
            private $unreadData;

            /** @var int */
            public $unreadChecks = 0;

            public function __construct(bool $unreadData)
            {
                $this->unreadData = $unreadData;
            }

            public function setTimes(float $lastRead, float $lastWrite): void
            {
                $this->last_read = $lastRead;
                $this->last_write = $lastWrite;
            }

            public function checkBroker(): void
            {
                $this->checkBrokerHeartbeat();
            }

            protected function hasUnreadData(): bool
            {
                $this->unreadChecks++;

                return $this->unreadData;
            }

            public function read($len)
            {
                return '';
            }

            public function write($data)
            {
            }

            public function close()
            {
            }

            protected function do_select(?int $sec, int $usec)
            {
                return 0;
            }

            public function connect()
            {
            }
        };
        $io->afterTune($heartbeat);
        if ($check) {
            $io->enableIncomingHeartbeatCheck();
        }
        $now = microtime(true);
        $io->setTimes($now - $secondsSinceRead, $now - $secondsSinceWrite);

        return $io;
    }
}
