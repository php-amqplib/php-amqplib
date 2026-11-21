<?php

namespace PhpAmqpLib\Tests\Unit\Connection\Heartbeat;

use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\Heartbeat\SIGHeartbeatSender;
use PhpAmqpLib\Tests\TestCaseCompat;

/**
 * @group signals
 * @group sig
 * @requires extension pcntl
 * @requires extension posix
 */
class SIGHeartbeatSenderTest extends TestCaseCompat
{
    private const HEARTBEAT_TIMEOUT = 2;

    /**
     * @test
     * @covers \PhpAmqpLib\Connection\Heartbeat\SIGHeartbeatSender::unregister()
     */
    public function unregister_terminates_and_reaps_the_child(): void
    {
        $sender = new SIGHeartbeatSender($this->createConnection());
        $sender->register();

        $childPid = $this->readChildPid($sender);
        self::assertGreaterThan(0, $childPid);

        $sender->unregister();

        self::assertSame(-1, pcntl_waitpid($childPid, $status, WNOHANG));
    }

    /**
     * @test
     * @covers \PhpAmqpLib\Connection\Heartbeat\SIGHeartbeatSender::periodicAlarm()
     */
    public function child_signals_its_parent_once_per_interval(): void
    {
        $sender = new SIGHeartbeatSender($this->createConnection());
        $sender->register();

        $received = 0;
        pcntl_signal(SIGUSR1, function () use (&$received) {
            $received++;
        });

        try {
            $interval = (int) ceil(self::HEARTBEAT_TIMEOUT / 2);
            $this->waitFor($interval * 2.5);

            self::assertGreaterThanOrEqual(2, $received);
        } finally {
            $sender->unregister();
        }
    }

    /**
     * @test
     * @covers \PhpAmqpLib\Connection\Heartbeat\SIGHeartbeatSender::periodicAlarm()
     */
    public function child_exits_when_its_parent_is_gone(): void
    {
        list($testSocket, $parentSocket) = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);

        $parentPid = pcntl_fork();

        if ($parentPid === 0) {
            fclose($testSocket);
            $sender = new SIGHeartbeatSender($this->createConnection());
            $sender->register();
            $forkedChildPid = $this->readChildPid($sender);
            fwrite($parentSocket, $forkedChildPid . "\n");
            fclose($parentSocket);

            while (true) {
                sleep(1);
            }
        }

        fclose($parentSocket);
        stream_set_timeout($testSocket, 10);    // causes fgets to return false after the deadline instead of blocking forever.
        $reportedChildPid = fgets($testSocket);
        self::assertNotFalse($reportedChildPid, 'Forked parent failed to report the heartbeat child PID');
        $childPid = (int) trim($reportedChildPid);
        fclose($testSocket);
        self::assertGreaterThan(0, $childPid);

        posix_kill($parentPid, SIGKILL);
        pcntl_waitpid($parentPid, $status);

        $interval = (int) ceil(self::HEARTBEAT_TIMEOUT / 2);
        $exited = $this->waitForProcessToExit($childPid, $interval + 3);
        posix_kill($childPid, SIGKILL);

        self::assertTrue($exited);
    }

    private function createConnection(): AbstractConnection
    {
        $connection = $this->getMockBuilder(AbstractConnection::class)
            ->onlyMethods(['isConnected', 'getHeartbeat', 'isWriting', 'getLastActivity'])
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $connection->method('getHeartbeat')->willReturn(self::HEARTBEAT_TIMEOUT);
        $connection->method('isConnected')->willReturn(true);
        $connection->method('isWriting')->willReturn(false);
        $connection->method('getLastActivity')->willReturn(time() + 99);

        return $connection;
    }

    private function readChildPid(SIGHeartbeatSender $sender): int
    {
        $property = $this->getClassProperty(SIGHeartbeatSender::class, 'childPid');

        return $property->getValue($sender);
    }

    private function waitFor(int|float $seconds): void
    {
        $deadline = microtime(true) + $seconds;

        while (microtime(true) < $deadline) {
            usleep(100000);
        }
    }

    private function waitForProcessToExit(int $pid, int|float $seconds): bool
    {
        $deadline = microtime(true) + $seconds;

        while (microtime(true) < $deadline) {
            // The process is not our child (or already reaped by us).
            // Detect "gone" without being fooled by zombies.
            if (!$this->isProcessRunning($pid)) {
                return true;
            }

            usleep(100000);
        }

        return false;
    }

    /**
     * Returns true only if the PID is in a *running* or *sleeping* state.
     * A zombie (Z) or a missing PID counts as "not running" — i.e. the
     * process has exited, which is what the test wants to observe.
     */
    private function isProcessRunning(int $pid): bool
    {
        // Linux / Linux-in-VM (Docker Desktop on macOS, etc.)
        if (is_dir('/proc')) {
            $statPath = '/proc/' . $pid . '/stat';

            if (!is_readable($statPath)) {
                // PID fully gone (reaped by init or never existed).
                return false;
            }

            // Format: pid (comm) state ...
            // The state character is the first field after the closing ')'.
            $data = trim((string) @file_get_contents($statPath));
            if ($data === '') {
                return false;
            }

            $afterParen = strstr($data, ')');
            if ($afterParen === false) {
                return false;
            }

            $fields = explode(' ', ltrim($afterParen, ') '));
            $state = $fields[0] ?? '';

            // Only zombie (Z) and dead (X/x) processes have exited.
            // Stopped and traced processes are still alive.
            return !in_array($state, ['Z', 'X', 'x'], true);
        }

        // macOS bare-metal: launchd reaps orphans quickly, so
        // posix_kill is reliable here.
        return posix_kill($pid, 0);
    }
}
