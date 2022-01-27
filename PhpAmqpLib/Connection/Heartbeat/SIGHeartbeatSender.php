<?php
namespace PhpAmqpLib\Connection\Heartbeat;

use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Exception\AMQPRuntimeException;

/**
 * Manages pcntl-based heartbeat sending for a {@link AbstractConnection}.
 * Unlike PCNTLHeartbeatSender, does not require the use of `SIGALRM` as the signal.
 * Any signal can be used. Default is `SIGUSR1`
 */
final class SIGHeartbeatSender
{
    /**
     * @var AbstractConnection
     */
    private $connection;

    private $signal;

    private $childPid;

    /**
     * @param AbstractConnection $connection
     * @throws AMQPRuntimeException
     */
    public function __construct(AbstractConnection $connection, $signal = SIGUSR1)
    {
        if (!$this->isSupported()) {
            throw new AMQPRuntimeException('Signal-based heartbeat sender is unsupported');
        }

        $this->signal = $signal;
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
            $this->registerListener($interval);
        }
    }

    public function unregister()
    {
        $this->connection = null;
        // restore default signal handler
        pcntl_signal($this->signal, SIG_IGN);
        if ($this->childPid) {
            posix_kill($this->childPid, SIGKILL);
        }
        $this->childPid = null;
    }

    /**
     * @param int $interval
     */
    private function registerListener($interval)
    {
        pcntl_async_signals(true);
        $this->periodicAlarm($interval);
        pcntl_signal($this->signal, function () use ($interval) {
            if (!$this->connection || $this->connection->isWriting()) {
                return;
            }

            if (!$this->connection->isConnected()) {
                $this->unregister();
                return;
            }

            if (time() > ($this->connection->getLastActivity() + $interval)) {
                $this->connection->checkHeartBeat();
            }
        });
    }

    private function periodicAlarm($interval)
    {
        $parent = getmypid();
        $pid = pcntl_fork();
        if(!$pid) {
            while (true){
                sleep($interval);
                posix_kill($parent, SIGUSR1);
            }
        } else {
            $this->childPid = $pid;
        }
    }
}
