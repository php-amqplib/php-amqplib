<?php

namespace PhpAmqpLib\Connection\Heartbeat;

use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Exception\AMQPRuntimeException;

/**
 * Manages pcntl-based heartbeat sending for a {@link AbstractConnection}.
 */
final class PCNTLHeartbeatSender
{
    /**
     * @var AbstractConnection
     */
    private $connection;

    /**
     * @param AbstractConnection $connection
     * @throws AMQPRuntimeException
     */
    public function __construct(AbstractConnection $connection)
    {
        if (!$this->isSupported()) {
            throw new AMQPRuntimeException('Signal-based heartbeat sender is unsupported');
        }

        $this->connection = $connection;
    }

    public function __destruct()
    {
        $this->unregister();
    }

    /**
     * @return bool
     */
    private function isSupported()
    {
        return extension_loaded('pcntl')
               && function_exists('pcntl_async_signals')
               && (defined('AMQP_WITHOUT_SIGNALS') ? !AMQP_WITHOUT_SIGNALS : true);
    }

    public function register()
    {
        if (!$this->connection) {
            throw new AMQPRuntimeException('Unable to re-register heartbeat sender');
        }

        if (!$this->connection->isConnected()) {
            throw new AMQPRuntimeException('Unable to register heartbeat sender, connection is not active');
        }

        $timeout = $this->connection->getHeartbeat();

        if ($timeout > 0) {
            $interval = ceil($timeout / 2);
            pcntl_async_signals(true);
            $this->registerListener($interval);
            pcntl_alarm($interval);
        }
    }

    public function unregister()
    {
        $this->connection = null;
        // restore default signal handler
        pcntl_signal(SIGALRM, SIG_IGN);
    }

    /**
     * @param int $interval
     */
    private function registerListener($interval)
    {
        pcntl_signal(SIGALRM, function () use ($interval) {
            if (!$this->connection) {
                return;
            }

            if (!$this->connection->isConnected()) {
                $this->unregister();
                return;
            }

            if ($this->connection->isWriting()) {
                pcntl_alarm($interval);
                return;
            }

            if (time() > ($this->connection->getLastActivity() + $interval)) {
                $this->connection->checkHeartBeat();
            }

            pcntl_alarm($interval);
        });
    }
}
