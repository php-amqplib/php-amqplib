<?php

namespace PhpAmqpLib\Wire\IO;

use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use PhpAmqpLib\Exception\AMQPDataReadException;
use PhpAmqpLib\Exception\AMQPIOException;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Helper\MiscHelper;
use PhpAmqpLib\Helper\SocketConstants;

class StreamIO extends AbstractIO
{
    /** @var string */
    protected $protocol;

    /** @var null|resource */
    protected $context;

    /** @var null|resource */
    private $sock;

    /**
     * @param string $host
     * @param int $port
     * @param float $connection_timeout
     * @param float $read_write_timeout
     * @param null $context
     * @param bool $keepalive
     * @param int $heartbeat
     * @param string|null $ssl_protocol
     */
    public function __construct(
        $host,
        $port,
        $connection_timeout,
        $read_write_timeout,
        $context = null,
        $keepalive = false,
        $heartbeat = 0,
        $ssl_protocol = null
    ) {
        // TODO FUTURE change comparison to <=
        // php-amqplib/php-amqplib#648, php-amqplib/php-amqplib#666
        /*
            TODO FUTURE enable this check
        if ($heartbeat !== 0 && ($read_write_timeout < ($heartbeat * 2))) {
            throw new \InvalidArgumentException('read_write_timeout must be at least 2x the heartbeat');
        }
         */

        $this->protocol = 'tcp';
        $this->host = $host;
        $this->port = $port;
        $this->connection_timeout = $connection_timeout;
        $this->read_timeout = $read_write_timeout;
        $this->write_timeout = $read_write_timeout;
        $this->context = $context;
        $this->keepalive = $keepalive;
        $this->heartbeat = $heartbeat;
        $this->initial_heartbeat = $heartbeat;
        $this->canDispatchPcntlSignal = $this->isPcntlSignalEnabled();

        if (!is_resource($this->context) || get_resource_type($this->context) !== 'stream-context') {
            $this->context = stream_context_create();
        }

        // tcp_nodelay was added in 7.1.0
        if (PHP_VERSION_ID >= 70100) {
            stream_context_set_option($this->context, 'socket', 'tcp_nodelay', true);
        }

        $options = stream_context_get_options($this->context);
        if (!empty($options['ssl'])) {
            if (isset($ssl_protocol)) {
                $this->protocol = $ssl_protocol;
            } else {
                $this->protocol = 'ssl';
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function connect()
    {
        $errstr = $errno = null;

        $remote = sprintf(
            '%s://%s:%s',
            $this->protocol,
            $this->host,
            $this->port
        );

        $this->set_error_handler();

        try {
            $this->sock = stream_socket_client(
                $remote,
                $errno,
                $errstr,
                $this->connection_timeout,
                STREAM_CLIENT_CONNECT,
                $this->context
            );
            $this->cleanup_error_handler();
        } catch (\ErrorException $e) {
            throw new AMQPIOException($e->getMessage());
        }

        if (false === $this->sock) {
            throw new AMQPIOException(
                sprintf(
                    'Error Connecting to server(%s): %s ',
                    $errno,
                    $errstr
                ),
                $errno
            );
        }

        if (!stream_socket_get_name($this->sock, true)) {
            throw new AMQPIOException(
                sprintf(
                    'Connection refused: %s ',
                    $remote
                )
            );
        }

        list($sec, $uSec) = MiscHelper::splitSecondsMicroseconds(max($this->read_timeout, $this->write_timeout));
        if (!stream_set_timeout($this->sock, $sec, $uSec)) {
            throw new AMQPIOException('Timeout could not be set');
        }

        // php cannot capture signals while streams are blocking
        if ($this->canDispatchPcntlSignal) {
            stream_set_blocking($this->sock, 0);
            stream_set_write_buffer($this->sock, 0);
            if (function_exists('stream_set_read_buffer')) {
                stream_set_read_buffer($this->sock, 0);
            }
        } else {
            stream_set_blocking($this->sock, true);
        }

        if ($this->keepalive) {
            $this->enable_keepalive();
        }
        $this->heartbeat = $this->initial_heartbeat;
    }

    /**
     * @inheritdoc
     */
    public function read($len)
    {
        $this->check_heartbeat();

        list($timeout_sec, $timeout_uSec) = MiscHelper::splitSecondsMicroseconds($this->read_timeout);

        $read_start = microtime(true);
        $read = 0;
        $data = '';

        while ($read < $len) {
            if (!is_resource($this->sock) || feof($this->sock)) {
                $this->close();
                throw new AMQPConnectionClosedException('Broken pipe or closed connection');
            }

            $this->set_error_handler();
            try {
                $buffer = fread($this->sock, ($len - $read));
                $this->cleanup_error_handler();
            } catch (\ErrorException $e) {
                throw new AMQPDataReadException($e->getMessage(), $e->getCode(), $e);
            }

            if ($buffer === false) {
                throw new AMQPDataReadException('Error receiving data');
            }

            if ($buffer === '') {
                $read_now = microtime(true);
                $t_read = $read_now - $read_start;
                if ($t_read > $this->read_timeout) {
                    throw new AMQPTimeoutException('Too many read attempts detected in StreamIO');
                }
                $this->select($timeout_sec, $timeout_uSec);

                continue;
            }

            $this->last_read = microtime(true);
            $read_start = $this->last_read;
            $read += mb_strlen($buffer, 'ASCII');
            $data .= $buffer;
        }

        if (mb_strlen($data, 'ASCII') !== $len) {
            throw new AMQPDataReadException(
                sprintf(
                    'Error reading data. Received %s instead of expected %s bytes',
                    mb_strlen($data, 'ASCII'),
                    $len
                )
            );
        }

        $this->last_read = microtime(true);

        return $data;
    }

    /**
     * @inheritdoc
     */
    public function write($data)
    {
        $this->checkBrokerHeartbeat();

        $written = 0;
        $len = mb_strlen($data, 'ASCII');
        $write_start = microtime(true);

        while ($written < $len) {
            if (!is_resource($this->sock) || feof($this->sock)) {
                $this->close();
                $constants = SocketConstants::getInstance();
                throw new AMQPConnectionClosedException('Broken pipe or closed connection', $constants->SOCKET_EPIPE);
            }

            $result = false;
            $this->set_error_handler();
            // OpenSSL's C library function SSL_write() can balk on buffers > 8192
            // bytes in length, so we're limiting the write size here. On both TLS
            // and plaintext connections, the write loop will continue until the
            // buffer has been fully written.
            // This behavior has been observed in OpenSSL dating back to at least
            // September 2002:
            // http://comments.gmane.org/gmane.comp.encryption.openssl.user/4361
            try {
                // check stream and prevent from high CPU usage
                $this->select_write();
                $buffer = mb_substr($data, $written, self::BUFFER_SIZE, 'ASCII');
                $result = fwrite($this->sock, $buffer);
                $this->cleanup_error_handler();
            } catch (\ErrorException $e) {
                $code = $this->last_error['errno'];
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
                        throw new AMQPRuntimeException($e->getMessage(), $code, $e);
                }
            }

            if ($result === false) {
                throw new AMQPRuntimeException('Error sending data');
            }

            if ($this->timed_out()) {
                throw AMQPTimeoutException::writeTimeout($this->write_timeout);
            }

            $now = microtime(true);
            if ($result > 0) {
                $this->last_write = $write_start = $now;
                $written += $result;
            } else {
                if (feof($this->sock)) {
                    $this->close();
                    throw new AMQPConnectionClosedException('Broken pipe or closed connection');
                }
                if (($now - $write_start) > $this->write_timeout) {
                    throw AMQPTimeoutException::writeTimeout($this->write_timeout);
                }
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function error_handler($errno, $errstr, $errfile, $errline, $errcontext = null)
    {
        $code = $this->extract_error_code($errstr);
        $constants = SocketConstants::getInstance();
        switch ($code) {
            // fwrite notice that the stream isn't ready - EAGAIN or EWOULDBLOCK
            case $constants->SOCKET_EAGAIN:
            case $constants->SOCKET_EWOULDBLOCK:
            // stream_select warning that it has been interrupted by a signal - EINTR
            case $constants->SOCKET_EINTR:
                return;
        }

        parent::error_handler($code > 0 ? $code : $errno, $errstr, $errfile, $errline, $errcontext);
    }

    public function close()
    {
        $this->disableHeartbeat();
        if (is_resource($this->sock)) {
            fclose($this->sock);
        }
        $this->sock = null;
        $this->last_read = 0;
        $this->last_write = 0;
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
    protected function do_select($sec, $usec)
    {
        $read = array($this->sock);
        $write = null;
        $except = null;

        return stream_select($read, $write, $except, $sec, $usec);
    }

    /**
     * @return int|bool
     */
    protected function select_write()
    {
        $read = $except = null;
        $write = array($this->sock);

        return stream_select($read, $write, $except, 0, 100000);
    }

    /**
     * @return mixed
     */
    protected function timed_out()
    {
        // get status of socket to determine whether or not it has timed out
        $info = stream_get_meta_data($this->sock);

        return $info['timed_out'];
    }

    /**
     * @throws \PhpAmqpLib\Exception\AMQPIOException
     */
    protected function enable_keepalive()
    {
        if ($this->protocol === 'ssl') {
            throw new AMQPIOException('Can not enable keepalive: ssl connection does not support keepalive (#70939)');
        }

        if ($this->protocol === 'tls') {
            throw new AMQPIOException('Can not enable keepalive: tls connection does not support keepalive (#70939)');
        }

        if (!function_exists('socket_import_stream')) {
            throw new AMQPIOException('Can not enable keepalive: function socket_import_stream does not exist');
        }

        if (!defined('SOL_SOCKET') || !defined('SO_KEEPALIVE')) {
            throw new AMQPIOException('Can not enable keepalive: SOL_SOCKET or SO_KEEPALIVE is not defined');
        }

        $socket = socket_import_stream($this->sock);
        socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, 1);
    }

    /**
     * @param string $message
     * @return int
     */
    protected function extract_error_code($message)
    {
        if (0 === strpos($message, 'stream_select():')) {
            $pattern = '/\s+\[(\d+)\]:\s+/';
        } else {
            $pattern = '/\s+errno=(\d+)\s+/';
        }
        $matches = array();
        $result = preg_match($pattern, $message, $matches);
        if ($result > 0) {
            return (int)$matches[1];
        }

        return 0;
    }
}
