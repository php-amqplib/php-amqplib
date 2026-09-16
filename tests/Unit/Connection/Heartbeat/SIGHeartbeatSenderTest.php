<?php

namespace PhpAmqpLib\Tests\Unit\Connection\Heartbeat;

use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\Heartbeat\SIGHeartbeatSender;
use PhpAmqpLib\Tests\TestCaseCompat;

/**
 * @group connection
 * @group signals
 * @group sig
 * @requires extension pcntl
 * @requires extension posix
 */
class SIGHeartbeatSenderTest extends TestCaseCompat
{
    /** @var int */
    private $heartbeatTimeout = 2;

    /** @var int */
    private $signal = SIGUSR1;

    /** @var SIGHeartbeatSender|null */
    private $sender;

    protected function tearDownCompat()
    {
        if ($this->sender !== null) {
            $this->sender->unregister();
        }
        $this->sender = null;
    }

    /**
     * @test
     */
    public function unregister_terminates_and_reaps_the_child()
    {
        $this->sender = new SIGHeartbeatSender($this->createConnection(), $this->signal);
        $this->sender->register();

        $childPid = $this->readChildPid($this->sender);
        self::assertGreaterThan(0, $childPid);

        $this->sender->unregister();

        self::assertSame(-1, pcntl_waitpid($childPid, $status, WNOHANG));
    }

    /**
     * @test
     */
    public function child_signals_its_parent_once_per_interval()
    {
        $this->sender = new SIGHeartbeatSender($this->createConnection(), $this->signal);
        $this->sender->register();

        $received = 0;
        pcntl_signal($this->signal, function () use (&$received) {
            $received++;
        });

        $interval = (int) ceil($this->heartbeatTimeout / 2);
        $this->waitFor($interval * 2.5);

        self::assertGreaterThanOrEqual(2, $received);
    }

    /**
     * @return AbstractConnection
     */
    private function createConnection()
    {
        $connection = $this->getMockBuilder(AbstractConnection::class)
            ->setMethods(['isConnected', 'getHeartbeat', 'isWriting', 'getLastActivity'])
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $connection->method('getHeartbeat')->willReturn($this->heartbeatTimeout);
        $connection->method('isConnected')->willReturn(true);
        $connection->method('isWriting')->willReturn(false);
        $connection->method('getLastActivity')->willReturn(time() + 99);

        return $connection;
    }

    /**
     * @param SIGHeartbeatSender $sender
     * @return int
     */
    private function readChildPid(SIGHeartbeatSender $sender)
    {
        $property = new \ReflectionProperty(SIGHeartbeatSender::class, 'childPid');
        $property->setAccessible(true);

        return $property->getValue($sender);
    }

    /**
     * @param float $seconds
     */
    private function waitFor($seconds)
    {
        $deadline = microtime(true) + $seconds;
        while (microtime(true) < $deadline) {
            usleep(100000);
        }
    }
}
