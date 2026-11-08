<?php

namespace PhpAmqpLib\Wire\IO;

use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use PhpAmqpLib\Exception\AMQPHeartbeatMissedException;
use PhpAmqpLib\Exception\AMQPIOWaitException;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use PhpAmqpLib\Wire\AMQPWriter;

abstract class AbstractIO
{
    const BUFFER_SIZE = 8192;

    /** @var null|AMQPConnectionConfig */
    protected $config;

    /** @var string */
    protected $host;

    /** @var int */
    protected $port;

    /** @var int|float */
    protected $connection_timeout;

    /** @var float */
    protected $read_timeout;

    /** @var float */
    protected $write_timeout;

    /** @var int */
    protected $heartbeat;

    /** @var int */
    protected $initial_heartbeat;

    /** @var bool */
    protected $keepalive;

    /** @var int|float */
    protected $last_read;

    /** @var int|float */
    protected $last_write;

    /**
     * When unread data from the broker was last seen waiting on the socket.
     * @var int|float
     */
    protected $last_unread_data = 0;

    /** @var bool */
    protected $broker_silence_check = false;

    /** @var \ErrorException|null */
    protected $last_error;

    /** @var bool */
    protected $canDispatchPcntlSignal = false;

    /**
     * @param int $len
     * @return string
     * @throws \PhpAmqpLib\Exception\AMQPIOException
     * @throws AMQPRuntimeException
     * @throws \PhpAmqpLib\Exception\AMQPSocketException
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException
     * @throws \PhpAmqpLib\Exception\AMQPConnectionClosedException
     */
    abstract public function read($len);

    /**
     * @param string $data
     * @throws \PhpAmqpLib\Exception\AMQPIOException
     * @throws \PhpAmqpLib\Exception\AMQPSocketException
     * @throws \PhpAmqpLib\Exception\AMQPConnectionClosedException
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException
     */
    abstract public function write($data);

    /**
     * @return void
     */
    abstract public function close();

    /**
     * @param int|null $sec
     * @param int $usec
     * @return int
     * @throws AMQPIOWaitException
     * @throws AMQPRuntimeException
     * @throws AMQPConnectionClosedException
     */
    public function select(?int $sec, int $usec = 0)
    {
        $this->check_heartbeat();
        $this->setErrorHandler();
        try {
            $result = $this->do_select($sec, $usec);
            $this->throwOnError();
        } catch (\ErrorException $e) {
            throw new AMQPIOWaitException($e->getMessage(), $e->getCode(), $e->getPrevious());
        } finally {
            $this->restoreErrorHandler();
        }

        if ($this->canDispatchPcntlSignal) {
            pcntl_signal_dispatch();
        }

        // no exception and false result - either timeout or signal was sent
        if ($result === false) {
            $result = 0;
        }

        return $result;
    }

    /**
     * @param int|null $sec
     * @param int $usec
     * @return int|bool
     * @throws AMQPConnectionClosedException
     */
    abstract protected function do_select(?int $sec, int $usec);

    /**
     * Set ups the connection.
     * @return void
     * @throws \PhpAmqpLib\Exception\AMQPIOException
     * @throws AMQPRuntimeException
     */
    abstract public function connect();

    /**
     * Set connection params connection tune(negotiation).
     * @param int $heartbeat
     */
    public function afterTune(int $heartbeat): void
    {
        $this->heartbeat = $heartbeat;
        $this->initial_heartbeat = $heartbeat;
    }

    /**
     * Heartbeat logic: check connection health here
     * @return void
     * @throws AMQPRuntimeException
     */
    public function check_heartbeat()
    {
        // ignore unless heartbeat interval is set
        if ($this->heartbeat !== 0 && $this->last_read > 0 && $this->last_write > 0) {
            // server has gone away
            $this->checkBrokerHeartbeat();

            // time for client to send a heartbeat
            $now = microtime(true);
            if (($this->heartbeat / 2) < $now - $this->last_write) {
                $this->write_heartbeat();
            }
        }
    }

    /**
     * @throws \PhpAmqpLib\Exception\AMQPHeartbeatMissedException
     */
    protected function checkBrokerHeartbeat(): void
    {
        if ($this->heartbeat > 0 && ($this->last_read > 0 || $this->last_write > 0)) {
            $lastActivity = $this->getLastActivity();
            $now = microtime(true);
            $limit = $this->heartbeat * 2 + 1;
            if (($now - $lastActivity) > $limit) {
                $this->close();
                throw new AMQPHeartbeatMissedException('Missed server heartbeat');
            }

            // Our own writes (heartbeats, publishes) say nothing about the broker: on a connection
            // that silently stopped delivering they keep the activity above fresh forever (#1227).
            // So the broker must also have sent something. A client that never reads, such as a
            // publisher, leaves the broker's heartbeats unread on the socket; those count too.
            // Opt-in: a proxy that sends heartbeats only to a client that sends nothing would trip it.
            if ($this->broker_silence_check && ($now - max($this->last_read, $this->last_unread_data)) > $limit) {
                if ($this->hasUnreadData()) {
                    $this->last_unread_data = $now;

                    return;
                }
                $this->close();
                throw new AMQPHeartbeatMissedException('Missed server heartbeat');
            }
        }
    }

    /**
     * Also require data from the broker within two heartbeat intervals.
     * @see \PhpAmqpLib\Connection\AMQPConnectionConfig::enableBrokerSilenceCheck()
     * @param bool $enabled
     * @return $this
     */
    public function enableBrokerSilenceCheck(bool $enabled = true)
    {
        $this->broker_silence_check = $enabled;

        return $this;
    }

    /**
     * Whether data from the broker is waiting on the socket, checked without blocking or reading.
     * An implementation that cannot tell must return true, so that the connection is kept.
     */
    protected function hasUnreadData(): bool
    {
        return true;
    }

    /**
     * @return float|int
     */
    public function getLastActivity()
    {
        return max($this->last_read, $this->last_write);
    }

    public function getReadTimeout(): float
    {
        return $this->read_timeout;
    }

    /**
     * @return $this
     */
    public function disableHeartbeat()
    {
        $this->initial_heartbeat = $this->heartbeat;
        $this->heartbeat = 0;

        return $this;
    }

    /**
     * @return $this
     */
    public function reenableHeartbeat()
    {
        $this->heartbeat = $this->initial_heartbeat;

        return $this;
    }

    /**
     * Sends a heartbeat message
     */
    protected function write_heartbeat()
    {
        $pkt = new AMQPWriter();
        $pkt->write_octet(8);
        $pkt->write_short(0);
        $pkt->write_long(0);
        $pkt->write_octet(0xCE);
        $this->write($pkt->getvalue());
    }

    /**
     * Begin tracking errors and set the error handler
     */
    protected function setErrorHandler(): void
    {
        $this->last_error = null;
        set_error_handler(array($this, 'error_handler'));
    }

    protected function throwOnError(): void
    {
        if ($this->last_error instanceof \ErrorException) {
            $error = $this->last_error;
            $this->last_error = null;
            throw $error;
        }
    }

    protected function restoreErrorHandler(): void
    {
        restore_error_handler();
    }

    /**
     * Internal error handler to deal with stream and socket errors.
     *
     * @param  int $errno
     * @param  string $errstr
     * @param  string $errfile
     * @param  int $errline
     * @return void
     */
    public function error_handler($errno, $errstr, $errfile, $errline): void
    {
        // throwing an exception in an error handler will halt execution
        // collect error continue
        $this->last_error = new \ErrorException($errstr, $errno, 1, $errfile, $errline, $this->last_error);
    }

    protected function isPcntlSignalEnabled(): bool
    {
        return extension_loaded('pcntl')
            && function_exists('pcntl_signal_dispatch')
            && (defined('AMQP_WITHOUT_SIGNALS') ? !AMQP_WITHOUT_SIGNALS : true);
    }
}
