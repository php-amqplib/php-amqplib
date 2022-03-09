<?php

namespace PhpAmqpLib\Wire\IO;

use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use PhpAmqpLib\Exception\AMQPIOException;
use PhpAmqpLib\Exception\AMQPSocketException;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Helper\MiscHelper;
use PhpAmqpLib\Helper\SocketConstants;

class SocketIO extends AbstractIO
{
    /** @var null|resource */
    private $sock;

    /**
     * @param string $host
     * @param int $port
     * @param int|float $read_timeout
     * @param bool $keepalive
     * @param int|float|null $write_timeout if null defaults to read timeout
     * @param int $heartbeat how often to send heartbeat. 0 means off
     */
    public function __construct(
        $host,
        $port,
        $read_timeout = 3,
        $keepalive = false,
        $write_timeout = null,
        $heartbeat = 0
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->read_timeout = (float)$read_timeout;
        $this->write_timeout = (float)($write_timeout ?: $read_timeout);
        $this->heartbeat = $heartbeat;
        $this->initial_heartbeat = $heartbeat;
        $this->keepalive = $keepalive;
        $this->canDispatchPcntlSignal = $this->isPcntlSignalEnabled();

        /*
            TODO FUTURE enable this check
            php-amqplib/php-amqplib#648, php-amqplib/php-amqplib#666
        if ($this->heartbeat !== 0 && ($this->read_timeout <= ($this->heartbeat * 2))) {
            throw new \InvalidArgumentException('read_timeout must be greater than 2x the heartbeat');
        }
        if ($this->heartbeat !== 0 && ($this->write_timeout <= ($this->heartbeat * 2))) {
            throw new \InvalidArgumentException('send_timeout must be greater than 2x the heartbeat');
        }
         */
    }

    /**
     * @inheritdoc
     */
    public function connect()
    {
        $this->sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        list($sec, $uSec) = MiscHelper::splitSecondsMicroseconds($this->write_timeout);
        socket_set_option($this->sock, SOL_SOCKET, SO_SNDTIMEO, array('sec' => $sec, 'usec' => $uSec));
        list($sec, $uSec) = MiscHelper::splitSecondsMicroseconds($this->read_timeout);
        socket_set_option($this->sock, SOL_SOCKET, SO_RCVTIMEO, array('sec' => $sec, 'usec' => $uSec));

        $this->setErrorHandler();
        try {
            $connected = socket_connect($this->sock, $this->host, $this->port);
            $this->throwOnError();
        } catch (\ErrorException $e) {
            $connected = false;
        } finally {
            $this->restoreErrorHandler();
        }
        if (!$connected) {
            $errno = socket_last_error($this->sock);
            $errstr = socket_strerror($errno);
            throw new AMQPIOException(sprintf(
                'Error Connecting to server (%s): %s',
                $errno,
                $errstr
            ), $errno);
        }

        socket_set_block($this->sock);
        socket_set_option($this->sock, SOL_TCP, TCP_NODELAY, 1);

        if ($this->keepalive) {
            $this->enable_keepalive();
        }

        $this->heartbeat = $this->initial_heartbeat;
    }

    /**
     * @inheritdoc
     */
    public function getSocket()
    {
        return $this->sock;
    }

    /**
     * @inheritdoc
     */
    public function read($len)
    {
        if (is_null($this->sock)) {
            throw new AMQPSocketException(sprintf(
                'Socket was null! Last SocketError was: %s',
                socket_strerror(socket_last_error())
            ));
        }

        $this->check_heartbeat();

        list($timeout_sec, $timeout_uSec) = MiscHelper::splitSecondsMicroseconds($this->read_timeout);
        $read_start = microtime(true);
        $read = 0;
        $data = '';
        while ($read < $len) {
            $buffer = null;
            $result = socket_recv($this->sock, $buffer, $len - $read, 0);
            if ($result === 0) {
                // From linux recv() manual:
                // When a stream socket peer has performed an orderly shutdown,
                // the return value will be 0 (the traditional "end-of-file" return).
                // http://php.net/manual/en/function.socket-recv.php#47182
                $this->close();
                throw new AMQPConnectionClosedException('Broken pipe or closed connection');
            }

            if (empty($buffer)) {
                $read_now = microtime(true);
                $t_read = $read_now - $read_start;
                if ($t_read > $this->read_timeout) {
                    throw new AMQPTimeoutException('Too many read attempts detected in SocketIO');
                }
                $this->select($timeout_sec, $timeout_uSec);
                continue;
            }

            $read += mb_strlen($buffer, 'ASCII');
            $data .= $buffer;
        }

        if (mb_strlen($data, 'ASCII') !== $len) {
            throw new AMQPIOException(sprintf(
                'Error reading data. Received %s instead of expected %s bytes',
                mb_strlen($data, 'ASCII'),
                $len
            ));
        }

        $this->last_read = microtime(true);

        return $data;
    }

    /**
     * @inheritdoc
     */
    public function write($data)
    {
        // Null sockets are invalid, throw exception
        if (is_null($this->sock)) {
            throw new AMQPSocketException(sprintf(
                'Socket was null! Last SocketError was: %s',
                socket_strerror(socket_last_error())
            ));
        }

        $this->checkBrokerHeartbeat();

        $written = 0;
        $len = mb_strlen($data, 'ASCII');
        $write_start = microtime(true);

        while ($written < $len) {
            $this->setErrorHandler();
            try {
                $this->select_write();
                $buffer = mb_substr($data, $written, self::BUFFER_SIZE, 'ASCII');
                $result = socket_write($this->sock, $buffer, self::BUFFER_SIZE);
                $this->throwOnError();
            } catch (\ErrorException $e) {
                $code = socket_last_error($this->sock);
                $constants = SocketConstants::getInstance();
                switch ($code) {
                    case $constants->SOCKET_EPIPE:
                    case $constants->SOCKET_ENETDOWN:
                    case $constants->SOCKET_ENETUNREACH:
                    case $constants->SOCKET_ENETRESET:
                    case $constants->SOCKET_ECONNABORTED:
                    case $constants->SOCKET_ECONNRESET:
                    case $constants->SOCKET_ECONNREFUSED:
                    case $constants->SOCKET_ETIMEDOUT:
                        $this->close();
                        throw new AMQPConnectionClosedException(socket_strerror($code), $code, $e);
                    default:
                        throw new AMQPIOException(sprintf(
                            'Error sending data. Last SocketError: %s',
                            socket_strerror($code)
                        ), $code, $e);
                }
            } finally {
                $this->restoreErrorHandler();
            }

            if ($result === false) {
                throw new AMQPIOException(sprintf(
                    'Error sending data. Last SocketError: %s',
                    socket_strerror(socket_last_error($this->sock))
                ));
            }

            $now = microtime(true);
            if ($result > 0) {
                $this->last_write = $write_start = $now;
                $written += $result;
            } else {
                if (($now - $write_start) > $this->write_timeout) {
                    throw AMQPTimeoutException::writeTimeout($this->write_timeout);
                }
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function close()
    {
        $this->disableHeartbeat();
        if (is_resource($this->sock) || is_a($this->sock, \Socket::class)) {
            socket_close($this->sock);
        }
        $this->sock = null;
        $this->last_read = 0;
        $this->last_write = 0;
    }

    /**
     * @inheritdoc
     */
    protected function do_select(?int $sec, int $usec)
    {
        if (!is_resource($this->sock) && !is_a($this->sock, \Socket::class)) {
            $this->sock = null;
            throw new AMQPConnectionClosedException('Broken pipe or closed connection', 0);
        }

        $read = array($this->sock);
        $write = null;
        $except = null;

        return socket_select($read, $write, $except, $sec, $usec);
    }

    /**
     * @return int|bool
     */
    protected function select_write()
    {
        $read = $except = null;
        $write = array($this->sock);

        return socket_select($read, $write, $except, 0, 100000);
    }

    /**
     * @throws \PhpAmqpLib\Exception\AMQPIOException
     */
    protected function enable_keepalive()
    {
        if (!defined('SOL_SOCKET') || !defined('SO_KEEPALIVE')) {
            throw new AMQPIOException('Can not enable keepalive: SOL_SOCKET or SO_KEEPALIVE is not defined');
        }

        socket_set_option($this->sock, SOL_SOCKET, SO_KEEPALIVE, 1);
    }

    /**
     * @inheritdoc
     */
    public function error_handler($errno, $errstr, $errfile, $errline, $errcontext = null)
    {
        $constants = SocketConstants::getInstance();
        // socket_select warning that it has been interrupted by a signal - EINTR
        if (isset($constants->SOCKET_EINTR) && false !== strrpos($errstr, socket_strerror($constants->SOCKET_EINTR))) {
            // it's allowed while processing signals
            return;
        }

        parent::error_handler($errno, $errstr, $errfile, $errline, $errcontext);
    }

    /**
     * @inheritdoc
     */
    protected function setErrorHandler(): void
    {
        parent::setErrorHandler();
        socket_clear_error($this->sock);
    }
}
