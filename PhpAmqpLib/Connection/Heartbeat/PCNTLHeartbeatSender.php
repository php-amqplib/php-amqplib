<?php

namespace PhpAmqpLib\Connection\Heartbeat;

use PhpAmqpLib\Exception\AMQPRuntimeException;

/**
 * @see AbstractSignalHeartbeatSender
 *
 * This version of a signal based heartbeat sendler relies on using SIGALRM and uses the OS to trigger an alarm
 * after a given time.
 */
final class PCNTLHeartbeatSender extends AbstractSignalHeartbeatSender
{
    public function register(): void
    {
        if (!$this->connection) {
            throw new AMQPRuntimeException('Unable to re-register heartbeat sender');
        }

        $timeout = $this->connection->getHeartbeat();

        if ($timeout > 0) {
            $interval = (int)ceil($timeout / 2);
            pcntl_async_signals(true);
            $this->registerListener($interval);
            pcntl_alarm($interval);
        }
    }

    public function unregister(): void
    {
        $this->connection = null;
        // restore default signal handler
        pcntl_signal(SIGALRM, SIG_IGN);
    }

    private function registerListener(int $interval): void
    {
        pcntl_signal(SIGALRM, function () use ($interval) {
            $this->handleSignal($interval);
            if ($this->connection) {
                pcntl_alarm($interval);
            }
        }, true);
    }
}
