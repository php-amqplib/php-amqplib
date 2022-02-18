<?php

namespace PhpAmqpLib\Connection\Heartbeat;

use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Exception\AMQPRuntimeException;

/**
 * Manages pcntl-based heartbeat sending for a {@link AbstractConnection}.
 */
abstract class AbstractSignalHeartbeatSender
{
    /**
     * @var AbstractConnection|null
     */
    protected $connection;

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
    protected function isSupported(): bool
    {
        return extension_loaded('pcntl')
               && function_exists('pcntl_async_signals')
               && (defined('AMQP_WITHOUT_SIGNALS') ? !AMQP_WITHOUT_SIGNALS : true);
    }

    /**
     * Starts the heartbeats
     */
    abstract public function register(): void;

    /**
     * Stops the heartbeats.
     */
    abstract public function unregister(): void;

    /**
     * Handles the heartbeat when a signal interrupt is received
     *
     * @param int $interval
     */
    protected function handleSignal(int $interval): void
    {
        if (!$this->connection) {
            return;
        }

        if (!$this->connection->isConnected()) {
            $this->unregister();
            return;
        }

        if ($this->connection->isWriting()) {
            return;
        }

        if (time() > ($this->connection->getLastActivity() + $interval)) {
            $this->connection->checkHeartBeat();
        }
    }
}
