<?php
namespace PhpAmqpLib\Connection\Heartbeat;

use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Exception\AMQPRuntimeException;

/**
 * Manages pcntl-based heartbeat sending for a {@link AbstractConnection}.
 */
final class PCNTLHeartbeatSender implements HeartbeatSenderInterface
{
    /**
     * @var AbstractConnection
     */
    private $connection;

    /**
     * @var bool
     */
    private $shutdown = false;

    /**
     * @var int|null
     */
    private $last_activity = null;

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

    /**
     * @return bool
     */
    private function isSupported()
    {
        return extension_loaded('pcntl')
            && function_exists('pcntl_async_signals')
            && (defined('AMQP_WITHOUT_SIGNALS') ? !AMQP_WITHOUT_SIGNALS : true);
    }

    public function signalActivity()
    {
        $this->last_activity = time();
    }

    /**
     * @param int $timeout
     */
    public function setHeartbeat($timeout)
    {
        if ($this->shutdown) {
            return;
        }

        if ($timeout > 0) {
            $interval = ceil($timeout / 2);
            pcntl_async_signals(true);
            $this->registerListener($interval);
            pcntl_alarm($interval);
        }
    }

    public function shutdown()
    {
        $this->shutdown = true;
        // restore default signal handler
        pcntl_signal(SIGALRM, SIG_IGN);
    }

    /**
     * @param int $interval
     */
    private function registerListener($interval)
    {
        pcntl_signal(SIGALRM, function () use ($interval) {
            if ($this->shutdown) {
                return;
            }

            if (time() > ($this->last_activity + $interval)) {
                $this->connection->checkHeartBeat();
            }

            pcntl_alarm($interval);
        });
    }
}
