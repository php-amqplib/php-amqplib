<?php
namespace PhpAmqpLib\Connection\Heartbeat;

use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Exception\AMQPRuntimeException;

/**
 * @see AbstractSignalHeartbeatSender
 * @since 3.2.0
 *
 * This version of a signal based heartbeat sender allows using any signal number. It forks the current process
 * to create a child process that periodically sends a signal to the parent process.
 * The default signal used is SIGUSR1
 */
final class SIGHeartbeatSender extends AbstractSignalHeartbeatSender
{
    /**
     * @var int the UNIX signal to be used for managing heartbeats
     */
    private $signal;

    /**
     * @var int the PID (process ID) of the child process sending regular signals to manage heartbeats
     */
    private $childPid;

    /**
     * @param AbstractConnection $connection
     * @param int $signal
     * @throws AMQPRuntimeException
     */
    public function __construct(AbstractConnection $connection, int $signal = SIGUSR1)
    {
        parent::__construct($connection);
        $this->signal = $signal;
    }

    public function register(): void
    {
        if (!$this->connection) {
            throw new AMQPRuntimeException('Unable to re-register heartbeat sender');
        }

        $timeout = $this->connection->getHeartbeat();

        if ($timeout > 0) {
            $interval = (int)ceil($timeout / 2);
            $this->registerListener($interval);
        }
    }

    public function unregister(): void
    {
        $this->connection = null;
        // restore default signal handler
        pcntl_signal($this->signal, SIG_IGN);
        if ($this->childPid > 0) {
            posix_kill($this->childPid, SIGKILL);
            pcntl_waitpid($this->childPid, $status);
        }
        $this->childPid = 0;
    }

    private function registerListener(int $interval): void
    {
        pcntl_async_signals(true);
        $this->periodicAlarm($interval);
        pcntl_signal($this->signal, function () use ($interval) {
            $this->handleSignal($interval);
        });
    }

    /**
     * Forks the current process to create a child process that will send periodic signals to the parent
     *
     * @param int $interval
     */
    private function periodicAlarm(int $interval): void
    {
        $parent = getmypid();
        $pid = pcntl_fork();
        if(!$pid) {
            while (true){
                $slept = sleep($interval);
                if ($slept !== 0) {
                    // interupted by signal from parent, exit immediately
                    die;
                }
                posix_kill($parent, $this->signal);
            }
        } else {
            $this->childPid = $pid;
        }
    }
}
